import { useRef, useState, type PointerEvent } from 'react';
import { Button } from '@/components/ui/Button';
import './forms.css';

export type Stroke = [number, number][];

const WIDTH = 600;
const HEIGHT = 200;

interface SignaturePadProps {
  onChange: (strokes: Stroke[]) => void;
  error?: string;
  id?: string;
}

/**
 * Lienzo de firma con Pointer Events (raton, lapiz y dedo). Solo envia
 * coordenadas: el SVG final lo construye el servidor.
 */
export function SignaturePad({ onChange, error, id = 'strokes' }: SignaturePadProps) {
  const svgRef = useRef<SVGSVGElement>(null);
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const latest = useRef<Stroke[]>([]);
  const drawing = useRef(false);

  // El ref guarda la ultima version para notificar sin efectos en el updater.
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
      // Algunos navegadores rechazan la captura; el trazo funciona igual.
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
        aria-label={hasInk ? 'Firma dibujada' : 'Recuadro para firmar'}
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
            Firma aquí con el dedo o el ratón
          </text>
        )}
      </svg>
      <div className="split">
        <span className="field__hint">Tu firma se guarda como trazo, junto con la fecha y la hora.</span>
        <Button size="sm" variant="quiet" icon="close" onClick={clear} disabled={!hasInk}>
          Borrar y repetir
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
