/**
 * Formato de fechas, dinero y nombres para es-CO. Las fechas llegan de MySQL
 * como "YYYY-MM-DD" o "YYYY-MM-DD HH:MM:SS" en hora local de la clinica.
 */

const LOCALE = 'es-CO';

export function parseDate(value: string | null | undefined): Date | null {
  if (!value || value.startsWith('0000')) return null;
  const normalized = value.length === 10 ? `${value}T00:00:00` : value.replace(' ', 'T');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value: string | null | undefined, options?: Intl.DateTimeFormatOptions): string {
  const date = parseDate(value);
  if (!date) return '—';
  return date.toLocaleDateString(LOCALE, options ?? { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatLongDate(value: string | null | undefined): string {
  return formatDate(value, { weekday: 'long', day: 'numeric', month: 'long' });
}

export function formatTime(value: string | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '—';
  return date.toLocaleTimeString(LOCALE, { hour: 'numeric', minute: '2-digit' });
}

export function formatDateTime(value: string | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '—';
  return `${formatDate(value)} · ${formatTime(value)}`;
}

export function formatMonth(period: string): string {
  const date = parseDate(`${period}-01`);
  return date ? date.toLocaleDateString(LOCALE, { month: 'short' }).replace('.', '') : period;
}

export function relativeDay(value: string | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '—';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(date);
  target.setHours(0, 0, 0, 0);
  const diff = Math.round((target.getTime() - today.getTime()) / 86_400_000);
  if (diff === 0) return 'Hoy';
  if (diff === 1) return 'Mañana';
  if (diff === -1) return 'Ayer';
  return formatDate(value, { weekday: 'short', day: 'numeric', month: 'short' });
}

export function formatMoney(amount: number | string | null | undefined, currency = 'COP'): string {
  const value = Number(amount ?? 0);
  try {
    return new Intl.NumberFormat(LOCALE, {
      style: 'currency',
      currency,
      maximumFractionDigits: currency === 'COP' ? 0 : 2,
    }).format(Number.isFinite(value) ? value : 0);
  } catch {
    return value.toLocaleString(LOCALE);
  }
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function initials(name: string | null | undefined): string {
  return (name ?? '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

export function fullName(person: { first_name?: string | null; last_name?: string | null }): string {
  return `${person.first_name ?? ''} ${person.last_name ?? ''}`.trim();
}

export function ageFrom(birthDate: string | null | undefined): number | null {
  const birth = parseDate(birthDate);
  if (!birth) return null;
  const now = new Date();
  let age = now.getFullYear() - birth.getFullYear();
  const beforeBirthday =
    now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate());
  if (beforeBirthday) age -= 1;
  return age;
}

export function toISODate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/** Saludo segun la hora: un detalle pequeno que hace la interfaz mas humana. */
export function greeting(date = new Date()): string {
  const hour = date.getHours();
  if (hour < 12) return 'Buenos días';
  if (hour < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

export function pluralize(count: number, singular: string, plural = `${singular}s`): string {
  return `${count} ${count === 1 ? singular : plural}`;
}

/**
 * Las etiquetas de severidad e interpretacion se guardan sin tildes junto a
 * cada resultado (son datos historicos). Solo se corrigen al mostrarlas.
 */
const ACCENTS: [RegExp, string][] = [
  [/\bMinima\b/g, 'Mínima'],
  [/\bminima\b/g, 'mínima'],
  [/\bDepresion\b/g, 'Depresión'],
  [/\bEstres\b/g, 'Estrés'],
  [/\bSintomatologia\b/g, 'Sintomatología'],
  [/\bsintomatologia\b/g, 'sintomatología'],
  [/\bIntervencion\b/g, 'Intervención'],
  [/\bintervencion\b/g, 'intervención'],
  [/\bpsicoeducacion\b/g, 'psicoeducación'],
  [/\bPsicoeducacion\b/g, 'Psicoeducación'],
  [/\bterapeutico\b/g, 'terapéutico'],
  [/\bevaluacion\b/g, 'evaluación'],
  [/\bevolucion\b/g, 'evolución'],
];

export function pretty(text: string | null | undefined): string {
  if (!text) return '';
  return ACCENTS.reduce((value, [pattern, replacement]) => value.replace(pattern, replacement), text);
}
