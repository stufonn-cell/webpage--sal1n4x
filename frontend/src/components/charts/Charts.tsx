import { useId, type CSSProperties } from 'react';
import './charts.css';

/**
 * Graficas SVG sin dependencias. Cada una incluye un resumen en texto para
 * lectores de pantalla y no anima nada mas alla de la entrada inicial.
 */

export interface Point {
  label: string;
  value: number;
  title?: string;
}

interface LineChartProps {
  points: Point[];
  max?: number;
  bands?: { from: number; to: number; tone: 'success' | 'info' | 'warning' | 'danger' }[];
  label: string;
  height?: number;
}

const WIDTH = 560;

export function LineChart({ points, max, bands = [], label, height = 180 }: LineChartProps) {
  const gradientId = useId();
  if (points.length === 0) return <p className="chart-empty">Aún no hay datos para graficar.</p>;

  const padX = 28;
  const padTop = 14;
  const padBottom = 28;
  const plotHeight = height - padTop - padBottom;
  const top = Math.max(1, max ?? Math.max(...points.map((point) => point.value)));
  const step = points.length > 1 ? (WIDTH - padX * 2) / (points.length - 1) : 0;

  const coords = points.map((point, index) => ({
    ...point,
    x: points.length > 1 ? padX + index * step : WIDTH / 2,
    y: padTop + plotHeight - (Math.min(point.value, top) / top) * plotHeight,
  }));

  const line = coords.map((c, index) => `${index === 0 ? 'M' : 'L'}${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' ');
  const area = `${line} L${coords[coords.length - 1].x.toFixed(1)} ${padTop + plotHeight} L${coords[0].x.toFixed(1)} ${padTop + plotHeight} Z`;
  const yFor = (value: number) => padTop + plotHeight - (Math.min(value, top) / top) * plotHeight;

  return (
    <figure className="chart">
      <svg viewBox={`0 0 ${WIDTH} ${height}`} role="img" aria-label={label}>
        <defs>
          <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stopColor="currentColor" stopOpacity="0.16" />
            <stop offset="1" stopColor="currentColor" stopOpacity="0" />
          </linearGradient>
        </defs>
        {bands.map((band) => (
          <rect
            key={`${band.from}-${band.to}`}
            className={`chart__band chart__band--${band.tone}`}
            x={padX}
            width={WIDTH - padX * 2}
            y={yFor(band.to)}
            height={Math.max(0, yFor(band.from) - yFor(band.to))}
          />
        ))}
        {[0, 0.5, 1].map((ratio) => (
          <line
            key={ratio}
            className="chart__grid"
            x1={padX}
            x2={WIDTH - padX}
            y1={padTop + plotHeight * ratio}
            y2={padTop + plotHeight * ratio}
          />
        ))}
        <path className="chart__area" d={area} fill={`url(#${gradientId})`} />
        <path className="chart__line" d={line} />
        {coords.map((c, index) => (
          <g key={index}>
            <circle className="chart__dot" cx={c.x} cy={c.y} r={4}>
              <title>{c.title ?? `${c.label}: ${c.value}`}</title>
            </circle>
            {(points.length <= 8 || index % Math.ceil(points.length / 8) === 0) && (
              <text className="chart__label" x={c.x} y={height - 8} textAnchor="middle">
                {c.label}
              </text>
            )}
          </g>
        ))}
      </svg>
      <figcaption className="visually-hidden">
        {label}: {points.map((point) => `${point.label} ${point.value}`).join(', ')}.
      </figcaption>
    </figure>
  );
}

export function BarChart({ points, label, height = 170 }: { points: Point[]; label: string; height?: number }) {
  if (points.length === 0) return <p className="chart-empty">Aún no hay datos para graficar.</p>;

  const padBottom = 26;
  const plotHeight = height - padBottom - 18;
  const top = Math.max(1, ...points.map((point) => point.value));
  const slot = WIDTH / points.length;
  const barWidth = Math.min(44, slot * 0.5);

  return (
    <figure className="chart">
      <svg viewBox={`0 0 ${WIDTH} ${height}`} role="img" aria-label={label}>
        {points.map((point, index) => {
          const barHeight = Math.max(2, (point.value / top) * plotHeight);
          const x = slot * index + (slot - barWidth) / 2;
          const y = 18 + plotHeight - barHeight;
          return (
            <g key={point.label} className="chart__bar-group" style={{ '--i': index } as CSSProperties}>
              <rect className="chart__bar" x={x} y={y} width={barWidth} height={barHeight} rx={5}>
                <title>{`${point.label}: ${point.value}`}</title>
              </rect>
              <text className="chart__value" x={x + barWidth / 2} y={y - 6} textAnchor="middle">
                {point.value}
              </text>
              <text className="chart__label" x={x + barWidth / 2} y={height - 6} textAnchor="middle">
                {point.label}
              </text>
            </g>
          );
        })}
      </svg>
      <figcaption className="visually-hidden">
        {label}: {points.map((point) => `${point.label} ${point.value}`).join(', ')}.
      </figcaption>
    </figure>
  );
}

interface Segment {
  label: string;
  value: number;
  tone: 'neutral' | 'success' | 'info' | 'warning' | 'danger' | 'primary';
}

/** Barra horizontal segmentada: mas legible que una dona para pocos valores. */
export function Distribution({ segments, label }: { segments: Segment[]; label: string }) {
  const total = segments.reduce((sum, segment) => sum + segment.value, 0);

  return (
    <figure className="distribution">
      <div className="distribution__bar" role="img" aria-label={label}>
        {total === 0 ? (
          <span className="distribution__empty" />
        ) : (
          segments
            .filter((segment) => segment.value > 0)
            .map((segment) => (
              <span
                key={segment.label}
                className={`distribution__segment distribution__segment--${segment.tone}`}
                style={{ flexGrow: segment.value }}
                title={`${segment.label}: ${segment.value}`}
              />
            ))
        )}
      </div>
      <figcaption>
        <ul className="distribution__legend">
          {segments.map((segment) => (
            <li key={segment.label}>
              <span className={`distribution__swatch distribution__segment--${segment.tone}`} aria-hidden="true" />
              <span>{segment.label}</span>
              <strong className="tabular">{segment.value}</strong>
            </li>
          ))}
        </ul>
      </figcaption>
    </figure>
  );
}
