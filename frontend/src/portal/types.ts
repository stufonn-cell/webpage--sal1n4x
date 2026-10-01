export interface PortalAppointment {
  id: number;
  starts_at: string;
  ends_at: string;
  modality: string;
  status: string;
  session_type: string | null;
  location: string | null;
  meeting_url: string | null;
  psychologist_name: string;
}

export interface PortalAssessment {
  id: number;
  instrument_code: string;
  instrument_name: string;
  instrument_domain: string;
  items_count: number;
  status: 'pending' | 'completed';
  total_score: number | null;
  max_score: number;
  severity: string | null;
  administered_at: string | null;
  created_at: string;
}

export interface PortalHomeData {
  patient: { first_name: string; last_name: string; record_number: string };
  appointments: PortalAppointment[];
  pending: PortalAssessment[];
  consents: { id: number; title: string; status: string; signed_at: string | null; created_at: string }[];
  completed: PortalAssessment[];
}

export const MODALITY_LABELS: Record<string, string> = {
  in_person: 'In person',
  online: 'Video call',
  phone: 'Phone call',
};

export const STATUS_LABELS: Record<string, string> = {
  scheduled: 'Scheduled',
  confirmed: 'Confirmed',
  completed: 'Completed',
  cancelled: 'Cancelled',
  no_show: 'You did not attend',
};
