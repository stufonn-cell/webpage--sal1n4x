import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { BarChart, Distribution } from '@/components/charts/Charts';
import { ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState, SkeletonRows } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, formatMoney, formatMonth, formatTime, fullName, greeting, pluralize, relativeDay } from '@/lib/format';
import { APPOINTMENT_STATUS_TONE, labelOf, RISK_TONE, useMeta } from '@/lib/meta';
import type { Appointment } from '@/lib/types';
import { useSession } from '@/session/SessionProvider';

interface Dashboard {
  metrics: {
    active_patients: number;
    total_patients: number;
    sessions_this_month: number;
    appointments_today: number;
    pending_assessments: number;
    high_risk: number;
    outstanding_balance: number;
    new_requests: number;
  };
  sessionsSeries: Record<string, number>;
  attendance: Record<string, number>;
  riskDistribution: Record<string, number>;
  today: Appointment[];
  upcoming: Appointment[];
  pendingAssessments: { id: number; instrument_code: string; created_at: string; patient_id: number; first_name: string; last_name: string }[];
  riskPatients: { id: number; first_name: string; last_name: string; record_number: string; risk_level: string }[];
  recentNotes: { id: number; session_date: string; session_number: number; is_locked: number; first_name: string; last_name: string }[];
}

function AppointmentRow({ appointment, statuses }: { appointment: Appointment; statuses?: { value: string; label: string }[] }) {
  return (
    <li>
      <Link className="list__item" to={`/app/agenda/${appointment.id}/editar`}>
        <span className="list__time">
          {formatTime(appointment.starts_at)}
        </span>
        <span className="list__main">
          <span className="list__title">{fullName(appointment)}</span>
          <span className="list__meta">{appointment.session_type || appointment.record_number}</span>
        </span>
        <Badge tone={APPOINTMENT_STATUS_TONE[appointment.status]}>{labelOf(statuses, appointment.status)}</Badge>
      </Link>
    </li>
  );
}

export default function DashboardPage() {
  const { user } = useSession();
  const { data: meta } = useMeta();
  const query = useQuery({ queryKey: ['dashboard'], queryFn: () => get<Dashboard>('/api/dashboard') });
  const data = query.data;
  const firstName = user?.full_name.split(' ')[0] ?? '';
  useDocumentTitle('Inicio');

  const metrics = data?.metrics;
  const todayCount = data?.today.length ?? 0;

  return (
    <>
      <PageHeader
        eyebrow={new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' })}
        title={`${greeting()}, ${firstName}`}
        subtitle={
          data
            ? todayCount === 0
              ? 'Hoy no hay citas en la agenda.'
              : `Hoy tienes ${pluralize(todayCount, 'cita')} en la agenda.`
            : 'Preparando tu resumen del día…'
        }
        actions={
          <>
            <ButtonLink to="/app/notas/nueva" icon="note">
              Nueva nota
            </ButtonLink>
            <ButtonLink to="/app/agenda/nueva" variant="primary" icon="calendar">
              Agendar cita
            </ButtonLink>
          </>
        }
      />

      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch} skeleton={<SkeletonRows rows={8} />}>
        {data && metrics && (
          <div className="section-gap">
            {metrics.new_requests > 0 && (
              <Alert tone="info" icon="inbox" title={`${pluralize(metrics.new_requests, 'solicitud nueva', 'solicitudes nuevas')} desde el sitio`}>
                <Link to="/app/solicitudes">Revisarlas y contactar a cada persona</Link>
              </Alert>
            )}

            <div className="panel stats">
              <Link className="stat" to="/app/pacientes?status=active">
                <span className="stat__label">Pacientes en tratamiento</span>
                <span className="stat__value">{metrics.active_patients}</span>
                <span className="stat__hint">de {metrics.total_patients} registrados</span>
              </Link>
              <Link className="stat" to="/app/notas">
                <span className="stat__label">Sesiones este mes</span>
                <span className="stat__value">{metrics.sessions_this_month}</span>
                <span className="stat__hint">notas registradas</span>
              </Link>
              <Link className="stat" to="/app/evaluaciones?status=pending">
                <span className="stat__label">Cuestionarios pendientes</span>
                <span className="stat__value">{metrics.pending_assessments}</span>
                <span className="stat__hint">asignados sin responder</span>
              </Link>
              <Link className="stat" to="/app/facturacion?status=issued">
                <span className="stat__label">Saldo por cobrar</span>
                <span className="stat__value">{formatMoney(metrics.outstanding_balance, meta?.settings.currency)}</span>
                <span className="stat__hint">facturas emitidas</span>
              </Link>
            </div>

            <div className="layout-2">
              <div className="section-gap">
                <Panel
                  title="Agenda de hoy"
                  titleId="hoy"
                  flush
                  actions={
                    <ButtonLink to="/app/agenda" size="sm" variant="quiet" iconRight="chevronRight">
                      Ver semana
                    </ButtonLink>
                  }
                >
                  {data.today.length === 0 ? (
                    <EmptyState icon="calendar" title="Un día sin citas" text="Buen momento para ponerse al día con las notas pendientes." />
                  ) : (
                    <ul className="list">
                      {data.today.map((appointment) => (
                        <AppointmentRow key={appointment.id} appointment={appointment} statuses={meta?.appointmentStatuses} />
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Próximas citas" titleId="proximas" flush>
                  {data.upcoming.length === 0 ? (
                    <EmptyState icon="calendar" title="No hay citas próximas" />
                  ) : (
                    <ul className="list">
                      {data.upcoming.map((appointment) => (
                        <li key={appointment.id}>
                          <Link className="list__item" to={`/app/agenda/${appointment.id}/editar`}>
                            <span className="list__main">
                              <span className="list__title">{fullName(appointment)}</span>
                              <span className="list__meta">
                                {relativeDay(appointment.starts_at)} · {formatTime(appointment.starts_at)}
                              </span>
                            </span>
                            <Badge tone="neutral" plain>
                              {labelOf(meta?.modalities, appointment.modality)}
                            </Badge>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Sesiones por mes" subtitle="Últimos seis meses" titleId="sesiones">
                  <BarChart
                    label="Sesiones registradas por mes"
                    points={Object.entries(data.sessionsSeries).map(([period, value]) => ({ label: formatMonth(period), value }))}
                  />
                </Panel>
              </div>

              <div className="section-gap">
                <Panel title="Requieren atención" subtitle="Riesgo moderado o alto" titleId="riesgo" flush>
                  {data.riskPatients.length === 0 ? (
                    <EmptyState icon="shield" title="Sin alertas de riesgo" text="Ningún paciente activo tiene riesgo moderado o alto." />
                  ) : (
                    <ul className="list">
                      {data.riskPatients.map((patient) => (
                        <li key={patient.id}>
                          <Link className="list__item" to={`/app/pacientes/${patient.id}`}>
                            <span className="list__main">
                              <span className="list__title">{fullName(patient)}</span>
                              <span className="list__meta">{patient.record_number}</span>
                            </span>
                            <Badge tone={RISK_TONE[patient.risk_level]}>{labelOf(meta?.riskLevels, patient.risk_level)}</Badge>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Cuestionarios por responder" titleId="pendientes" flush>
                  {data.pendingAssessments.length === 0 ? (
                    <EmptyState icon="clipboard" title="Nada pendiente" />
                  ) : (
                    <ul className="list">
                      {data.pendingAssessments.map((item) => (
                        <li key={item.id}>
                          <Link className="list__item" to={`/app/pacientes/${item.patient_id}`}>
                            <span className="list__main">
                              <span className="list__title">{fullName(item)}</span>
                              <span className="list__meta">Asignado el {formatDate(item.created_at)}</span>
                            </span>
                            <Badge plain>{item.instrument_code}</Badge>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Asistencia" subtitle="Últimos 90 días" titleId="asistencia">
                  <Distribution
                    label="Distribución de estados de las citas"
                    segments={Object.entries(data.attendance).map(([status, value]) => ({
                      label: labelOf(meta?.appointmentStatuses, status),
                      value,
                      tone: APPOINTMENT_STATUS_TONE[status] ?? 'neutral',
                    }))}
                  />
                </Panel>

                <Panel title="Notas recientes" titleId="notas" flush>
                  <ul className="list">
                    {data.recentNotes.map((note) => (
                      <li key={note.id}>
                        <Link className="list__item" to={`/app/notas/${note.id}`}>
                          <span className="list__main">
                            <span className="list__title">{fullName(note)}</span>
                            <span className="list__meta">
                              Sesión {note.session_number} · {formatDate(note.session_date)}
                            </span>
                          </span>
                          {note.is_locked ? <Badge tone="success">Firmada</Badge> : <Badge tone="warning">Borrador</Badge>}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </Panel>
              </div>
            </div>
          </div>
        )}
      </QueryState>
    </>
  );
}
