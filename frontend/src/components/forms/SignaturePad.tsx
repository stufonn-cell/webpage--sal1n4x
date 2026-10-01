import { useRef, useState, type PointerEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { documentText } from '@/lib/documentText';
import type { DocumentLanguage } from '@/lib/types';
import './forms.css';

export type Stroke = [number, number][];

const WIDTH = 600;
const HEIGHT = 200;

interface SignaturePadProps {
  onChange: (strokes: Stroke[]) => void;
  error?: string;
  id?: string;
  /** Language of the document being signed. */
  language?: DocumentLanguage;
}

/**
 * Signature canvas using Pointer Events (mouse, pen and finger). It only sends
 * coordinates: the server builds the final SVG.
 */
export function SignaturePad({ onChange, error, id = 'strokes', language = 'en' }: SignaturePadProps) {
  const t = documentText(language).signaturePad;
  const svgRef = useRef<SVGSVGElement>(null);
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const latest = useRef<Stroke[]>([]);
  const drawing = useRef(false);

  // The ref keeps the latest version so we can notify without side effects in the updater.
  const update = (fn: (current: Stroke[]) => Stroke[]) =>
    setStrokes((current) => {
      const next = fn(current);
      latest.current = next;
      return next;
    });

  const point = (event: PointerEvent<SVGSVGElement>): [number, number] => {
    const rect = svgRef.current!.getBoundingClientRect();
    return [
      Math.round(((event.clientX - rect.left) / rect.width) * WIDTH),
      Math.round(((event.clientY - rect.top) / rect.height) * HEIGHT),
    ];
  };

  const start = (event: PointerEvent<SVGSVGElement>) => {
    try {
      event.currentTarget.setPointerCapture(event.pointerId);
    } catch {
      // Some browsers reject the capture; drawing still works.
    }
    drawing.current = true;
    const first = point(event);
    update((current) => [...current, [first]]);
  };

  const move = (event: PointerEvent<SVGSVGElement>) => {
    if (!drawing.current) return;
    const next = point(event);
    update((current) => {
      const copy = current.slice();
      copy[copy.length - 1] = [...copy[copy.length - 1], next];
      return copy;
    });
  };

  const end = () => {
    if (!drawing.current) return;
    drawing.current = false;
    onChange(latest.current.filter((stroke) => stroke.length > 1));
  };

  const clear = () => {
    update(() => []);
    onChange([]);
  };

  const hasInk = strokes.some((stroke) => stroke.length > 1);

  return (
    <div className={error ? 'signature has-error' : 'signature'}>
      <svg
        ref={svgRef}
        id={id}
        className="signature__pad"
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        role="img"
        aria-label={hasInk ? t.drawn : t.box}
        tabIndex={0}
        onPointerDown={start}
        onPointerMove={move}
        onPointerUp={end}
        onPointerCancel={end}
        onPointerLeave={end}
      >
        <line className="signature__baseline" x1="40" x2="560" y1="150" y2="150" />
        {strokes
          .filter((stroke) => stroke.length > 1)
          .map((stroke, index) => (
            <path key={index} className="signature__ink" d={`M${stroke.map((p) => p.join(' ')).join(' L')}`} />
          ))}
        {!hasInk && (
          <text className="signature__placeholder" x="300" y="110" textAnchor="middle">
            {t.placeholder}
          </text>
        )}
      </svg>
      <div className="split">
        <span className="field__hint">{t.hint}</span>
        <Button size="sm" variant="quiet" icon="close" onClick={clear} disabled={!hasInk}>
          {t.clear}
        </Button>
      </div>
      {error && (
        <span className="field__error" role="alert">
          {error}
        </span>
      )}
    </div>
  );
}
