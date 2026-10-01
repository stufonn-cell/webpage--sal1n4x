import type { Appointment, Assessment, Consent, DocumentRow, Invoice, Note, Patient, SeriesPoint, TimelineEvent } from '@/lib/types';

export interface Diagnosis {
  id: number;
  system: 'icd11' | 'icd10' | 'dsm5';
  code: string;
  /** ICD-10 equivalent stored with ICD-11 and DSM-5 diagnoses (dual coding). */
  icd10_code: string | null;
  title: string;
  status: string;
  is_primary: boolean;
  onset_date: string | null;
  notes: string | null;
  created_at: string;
  /** Code RIPS will receive, or null when it is not reportable yet. */
  rips_code: string | null;
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
