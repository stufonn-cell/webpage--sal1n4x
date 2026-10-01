import { useQuery } from '@tanstack/react-query';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatBytes, formatDate, formatTime } from '@/lib/format';
import { APPOINTMENT_STATUS_TONE } from '@/lib/meta';
import type { DocumentRow } from '@/lib/types';
import { MODALITY_LABELS, STATUS_LABELS, type PortalAppointment } from '../types';

export function AppointmentsPage() {
  useDocumentTitle('Mis citas');
  const query = useQuery({ queryKey: ['portal', 'appointments'], queryFn: () => get<PortalAppointment[]>('/api/portal/appointments') });
  const now = Date.now();
  const upcoming = (query.data ?? []).filter((item) => new Date(item.starts_at.replace(' ', 'T')).getTime() >= now).reverse();
  const past = (query.data ?? []).filter((item) => new Date(item.starts_at.replace(' ', 'T')).getTime() < now);

  const Row = ({ appointment }: { appointment: PortalAppointment }) => (
    <li className="list__item">
      <span className="list__main">
        <span className="list__title">{formatDate(appointment.starts_at, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</span>
        <span className="list__meta">
          {formatTime(appointment.starts_at)} · {MODALITY_LABELS[appointment.modality]} · {appointment.psychologist_name}
        </span>
      </span>
      {appointment.meeting_url && appointment.modality !== 'in_person' && ['scheduled', 'confirmed'].includes(appointment.status) && (
        <a className="btn btn--sm" href={appointment.meeting_url} target="_blank" rel="noopener noreferrer">
          <Icon name="video" size={15} />
          <span className="btn__label">Enlace</span>
        </a>
      )}
      <Badge tone={APPOINTMENT_STATUS_TONE[appointment.status]}>{STATUS_LABELS[appointment.status] ?? appointment.status}</Badge>
    </li>
  );

  return (
    <>
      <PageHeader title="Mis citas" subtitle="Si necesitas cambiar una cita, comunícate con el consultorio." />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        <div className="section-gap">
          <Panel title="Próximas" titleId="proximas" flush>
            {upcoming.length === 0 ? (
              <EmptyState icon="calendar" title="No tienes citas próximas" />
            ) : (
              <ul className="list">
                {upcoming.map((appointment) => (
                  <Row key={appointment.id} appointment={appointment} />
                ))}
              </ul>
            )}
          </Panel>
          {past.length > 0 && (
            <Panel title="Anteriores" titleId="anteriores" flush>
              <ul className="list">
                {past.map((appointment) => (
                  <Row key={appointment.id} appointment={appointment} />
                ))}
              </ul>
            </Panel>
          )}
        </div>
      </QueryState>
    </>
  );
}

export function DocumentsPage() {
  useDocumentTitle('Mis documentos');
  const query = useQuery({ queryKey: ['portal', 'documents'], queryFn: () => get<DocumentRow[]>('/api/portal/documents') });

  return (
    <>
      <PageHeader title="Mis documentos" subtitle="Informes y documentos que tu profesional compartió contigo." />
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.length === 0 ? (
            <EmptyState icon="file" title="Aún no hay documentos" />
          ) : (
            <ul className="list">
              {query.data?.map((document) => (
                <li key={document.id} className="list__item">
                  <Icon name="file" size={18} />
                  <span className="list__main">
                    <span className="list__title">{document.title}</span>
                    <span className="list__meta">
                      {formatDate(document.created_at)} · {formatBytes(document.size_bytes)}
                    </span>
                  </span>
                  <a className="btn btn--sm" href={`/api/documents/${document.id}/download`}>
                    <Icon name="download" size={15} />
                    <span className="btn__label">Descargar</span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </QueryState>
      </Panel>
    </>
  );
}
