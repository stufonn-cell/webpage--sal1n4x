import { useQuery } from '@tanstack/react-query';
import type { Tone } from '@/components/ui/Display';
import { get } from './api';
import type { Meta, Option } from './types';

/** Backend catalogs (status labels, patient lists, etc.). Staff only: pass enabled=false elsewhere. */
export function useMeta({ enabled = true }: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: ['meta'],
    queryFn: () => get<Meta>('/api/meta'),
    staleTime: 60_000,
    enabled,
  });
}

export function labelOf(options: Option[] | undefined, value: string | null | undefined): string {
  if (!value) return '—';
  return options?.find((option) => option.value === value)?.label ?? value;
}

export const RISK_TONE: Record<string, Tone> = {
  none: 'neutral',
  low: 'info',
  moderate: 'warning',
  high: 'danger',
};

export const PATIENT_STATUS_TONE: Record<string, Tone> = {
  active: 'success',
  on_hold: 'warning',
  discharged: 'neutral',
};

export const APPOINTMENT_STATUS_TONE: Record<string, Tone> = {
  scheduled: 'info',
  confirmed: 'primary',
  completed: 'success',
  cancelled: 'neutral',
  no_show: 'warning',
};

export const INVOICE_STATUS_TONE: Record<string, Tone> = {
  draft: 'neutral',
  issued: 'warning',
  paid: 'success',
  void: 'neutral',
};

export const REQUEST_STATUS_TONE: Record<string, Tone> = {
  new: 'primary',
  contacted: 'info',
  scheduled: 'success',
  dismissed: 'neutral',
};

/**
 * Tone for the severity label returned by automatic scoring, in English or in
 * Spanish (reports can be printed in either). Checked in order, so
 * "Moderately severe" / "Moderadamente severa" is danger because the severe
 * check comes first.
 */
export function severityTone(severity: string | null | undefined): Tone {
  const value = (severity ?? '').toLowerCase();
  if (/\b(?:severe|extreme|high|sever|extrem|alt[oa]\b)/.test(value)) return 'danger';
  if (/\b(?:moderate|moderad)/.test(value)) return 'warning';
  if (/\b(?:mild|low|leve|baj[oa]\b)/.test(value)) return 'info';
  if (/\b(?:minimal|normal|none|adequate|well-?being|m[ií]nima|adecuad)/.test(value)) return 'success';
  return 'neutral';
}
