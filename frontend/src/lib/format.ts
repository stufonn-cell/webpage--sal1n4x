/**
 * Date, money and name formatting. The interface uses en-US; documents printed
 * in Spanish pass the es-CO locale. Dates arrive from MySQL as
 * "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS" in the practice's local time.
 */

const LOCALE = 'en-US';

export function parseDate(value: string | null | undefined): Date | null {
  if (!value || value.startsWith('0000')) return null;
  const normalized = value.length === 10 ? `${value}T00:00:00` : value.replace(' ', 'T');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value: string | null | undefined, options?: Intl.DateTimeFormatOptions, locale = LOCALE): string {
  const date = parseDate(value);
  if (!date) return '—';
  return date.toLocaleDateString(locale, options ?? { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatLongDate(value: string | null | undefined): string {
  return formatDate(value, { weekday: 'long', day: 'numeric', month: 'long' });
}

export function formatTime(value: string | null | undefined, locale = LOCALE): string {
  const date = parseDate(value);
  if (!date) return '—';
  return date.toLocaleTimeString(locale, { hour: 'numeric', minute: '2-digit' });
}

export function formatDateTime(value: string | null | undefined, locale = LOCALE): string {
  const date = parseDate(value);
  if (!date) return '—';
  return `${formatDate(value, undefined, locale)} · ${formatTime(value, locale)}`;
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
  if (diff === 0) return 'Today';
  if (diff === 1) return 'Tomorrow';
  if (diff === -1) return 'Yesterday';
  return formatDate(value, { weekday: 'short', day: 'numeric', month: 'short' });
}

export function formatMoney(amount: number | string | null | undefined, currency = 'COP', locale = LOCALE): string {
  const value = Number(amount ?? 0);
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      maximumFractionDigits: currency === 'COP' ? 0 : 2,
    }).format(Number.isFinite(value) ? value : 0);
  } catch {
    return value.toLocaleString(locale);
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

/** Greeting based on the time of day: a small touch that makes the interface feel more human. */
export function greeting(date = new Date()): string {
  const hour = date.getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 19) return 'Good afternoon';
  return 'Good evening';
}

export function pluralize(count: number, singular: string, plural = `${singular}s`): string {
  return `${count} ${count === 1 ? singular : plural}`;
}
