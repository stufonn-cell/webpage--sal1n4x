import { useQuery } from '@tanstack/react-query';
import type { Tone } from '@/components/ui/Display';
import { get } from './api';
import type { Meta, Option } from './types';

/** Catalogos del backend (etiquetas de estados, listas de pacientes, etc.). */
export function useMeta() {
  return useQuery({
    queryKey: ['meta'],
    queryFn: () => get<Meta>('/api/meta'),
    staleTime: 60_000,
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

/** Tono segun la severidad textual que devuelve la correccion automatica. */
export function severityTone(severity: string | null | undefined): Tone {
  const value = (severity ?? '').toLowerCase();
  if (/sever|grave|alto|alta|extrem/.test(value)) return 'danger';
  if (/moderad/.test(value)) return 'warning';
  if (/leve|bajo|baja|medio/.test(value)) return 'info';
  if (/minim|normal|adecuad|sin|bienestar/.test(value)) return 'success';
  return 'neutral';
}
