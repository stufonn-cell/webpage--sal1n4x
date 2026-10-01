import type { Appointment, Assessment, Consent, DocumentRow, Invoice, Note, Patient, SeriesPoint, TimelineEvent } from '@/lib/types';

export interface Diagnosis {
  id: number;
  system: 'icd10' | 'dsm5';
  code: string;
  title: string;
  status: string;
  onset_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface PatientBundle {
  patient: Patient;
  notes: Note[];
  assessments: Assessment[];
  series: { code: string; maxScore: number; points: SeriesPoint[] }[];
  moodSeries: { session_date: string; mood_score: number }[];
  timeline: TimelineEvent[];
  diagnoses: Diagnosis[];
  appointments: Appointment[];
  documents: DocumentRow[];
  consents: Consent[];
  invoices: Invoice[];
  portalAccount: { username: string; is_active: boolean; last_login_at: string | null } | null;
}
