/**
 * Formas de los datos que devuelve la API. Los nombres de campo siguen las
 * columnas de la base (snake_case) para no duplicar un mapeo en cada modulo.
 */

export type Role = 'admin' | 'psychologist' | 'assistant' | 'patient';
export type RiskLevel = 'none' | 'low' | 'moderate' | 'high';
export type Theme = 'light' | 'dark';

export interface Option {
  value: string;
  label: string;
}

export interface User {
  id: number;
  username: string;
  email: string;
  full_name: string;
  role: Role;
  license_number: string | null;
  specialty: string | null;
  phone: string | null;
  theme: Theme;
  patient_id: number | null;
  is_active: boolean;
  last_login_at: string | null;
  show_on_site: boolean;
  public_bio: string | null;
  record_number?: string | null;
}

export interface DemoAccount {
  role: string;
  identifier: string;
  password: string;
}

export interface SessionData {
  user: User | null;
  csrfToken: string;
  clinic: { name: string; tagline: string };
  demoAccounts: DemoAccount[];
}

export interface Page<T> {
  rows: T[];
  total: number;
  page: number;
  pages: number;
}

export interface Meta {
  patientStatuses: Option[];
  riskLevels: Option[];
  genders: Option[];
  appointmentStatuses: Option[];
  modalities: Option[];
  noteFormats: Option[];
  interventions: string[];
  documentCategories: Option[];
  invoiceStatuses: Option[];
  paymentMethods: Option[];
  diagnosisStatuses: Option[];
  requestStatuses: Option[];
  requestModalities: Option[];
  requestAttendees: Option[];
  requestContact: Option[];
  requestTimes: Option[];
  consentTemplates: Option[];
  instruments: Option[];
  roles: Option[];
  psychologists: { id: number; full_name: string }[];
  patients: { id: number; label: string }[];
  settings: {
    currency: string;
    session_duration: number;
    default_fee: number;
    working_hours_start: string;
    working_hours_end: string;
    note_lock_hours: number;
  };
  nextRecordNumber: string;
  nextInvoiceNumber: string;
}

export interface PatientSummary {
  id: number;
  record_number: string;
  first_name: string;
  last_name: string;
  birth_date: string | null;
  email: string | null;
  phone: string | null;
  status: string;
  risk_level: RiskLevel;
  psychologist_name: string | null;
  last_session: string | null;
  sessions_count: number;
}

export interface Patient extends PatientSummary {
  gender: string;
  document_type: string | null;
  document_id: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
  occupation: string | null;
  marital_status: string | null;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  referred_by: string | null;
  reason_for_consult: string | null;
  relevant_history: string | null;
  current_medication: string | null;
  psychologist_id: number | null;
  created_at: string;
}

export interface Appointment {
  id: number;
  patient_id: number;
  psychologist_id: number;
  starts_at: string;
  ends_at: string;
  modality: string;
  status: string;
  session_type: string | null;
  location: string | null;
  meeting_url: string | null;
  fee: number | string;
  notes: string | null;
  first_name?: string;
  last_name?: string;
  record_number?: string;
  psychologist_name?: string;
}

export interface Note {
  id: number;
  patient_id: number;
  author_id: number;
  appointment_id: number | null;
  format: string;
  session_number: number;
  session_date: string;
  subjective: string | null;
  objective: string | null;
  assessment: string | null;
  plan: string | null;
  interventions: string | null;
  homework: string | null;
  mood_score: number | null;
  risk_level: RiskLevel;
  is_locked: number;
  locked_at: string | null;
  first_name?: string;
  last_name?: string;
  record_number?: string;
  birth_date?: string | null;
  document_id?: string | null;
  author_name?: string;
  license_number?: string | null;
  created_at: string;
}

export interface InstrumentBand {
  min: number;
  max: number;
  label: string;
  interpretation: string;
}

export interface Instrument {
  code: string;
  name: string;
  domain: string;
  window: string;
  description: string;
  items: string[];
  scale: { label: string; value: number }[];
  bands: InstrumentBand[];
  subscales: string[];
  criticalItems: number[];
  maxScore: number;
}

export interface Assessment {
  id: number;
  patient_id: number;
  instrument_code: string;
  status: 'pending' | 'completed';
  total_score: number | null;
  severity: string | null;
  interpretation: string | null;
  clinician_notes: string | null;
  administered_at: string | null;
  created_at: string;
  first_name?: string;
  last_name?: string;
  record_number?: string;
  assigned_by_name?: string | null;
}

export interface SeriesPoint {
  administered_at: string;
  total_score: number;
  severity: string;
}

export interface Consent {
  id: number;
  patient_id: number;
  template_code: string;
  title: string;
  body?: string;
  status: 'pending' | 'signed' | 'revoked';
  signed_name: string | null;
  signed_at: string | null;
  signed_ip?: string | null;
  signature_svg: string;
  created_at: string;
  first_name?: string;
  last_name?: string;
  record_number?: string;
}

export interface DocumentRow {
  id: number;
  patient_id: number;
  title: string;
  category: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  created_at: string;
  first_name?: string;
  last_name?: string;
  record_number?: string;
  uploaded_by_name?: string | null;
}

export interface Invoice {
  id: number;
  patient_id: number;
  number: string;
  issued_at: string;
  due_at: string | null;
  subtotal: number | string;
  tax: number | string;
  total: number | string;
  paid?: number | string;
  status: string;
  notes: string | null;
  first_name?: string;
  last_name?: string;
  record_number?: string;
  document_id?: string | null;
  email?: string | null;
}

export interface TimelineEvent {
  at: string;
  type: 'note' | 'appointment' | 'assessment';
  title: string;
  meta: string;
  link: string;
}

export interface AppointmentRequest {
  id: number;
  full_name: string;
  email: string;
  phone: string | null;
  contact_preference: string;
  modality: string;
  attendee: string;
  preferred_times: string | null;
  message: string | null;
  status: string;
  created_at: string;
  handled_at: string | null;
  professional_name: string | null;
  handled_by_name: string | null;
}

export interface AuditEntry {
  id: number;
  user_id: number | null;
  full_name: string | null;
  action: string;
  entity: string;
  entity_id: number | null;
  ip_address: string | null;
  created_at: string;
}

export interface PublicProfessional {
  id: number;
  full_name: string;
  specialty: string | null;
  license_number: string | null;
  public_bio: string | null;
}

export interface PublicSite {
  clinic: {
    clinic_name: string;
    clinic_tagline: string;
    clinic_email: string;
    clinic_phone: string;
    clinic_address: string;
    session_duration: string;
    working_hours_start: string;
    working_hours_end: string;
    clinic_about: string;
    whatsapp_number: string;
    crisis_line: string;
  };
  professionals: PublicProfessional[];
  approaches: string[];
  instruments: { code: string; name: string; domain: string }[];
  form: {
    modalities: Option[];
    attendees: Option[];
    contactPreferences: Option[];
    times: Option[];
  };
}
