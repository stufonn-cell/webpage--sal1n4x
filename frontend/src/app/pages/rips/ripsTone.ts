import type { Tone } from '@/components/ui/Display';
import type { RipsStatus } from '@/lib/types';

export const RIPS_STATUS_TONE: Record<RipsStatus, Tone> = {
  generated: 'info',
  validated: 'success',
  rejected: 'danger',
};
