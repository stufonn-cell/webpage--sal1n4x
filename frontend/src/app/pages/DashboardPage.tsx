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
      <Link className="list__item" to={`/app/schedule/${appointment.id}/edit`}>
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
  useDocumentTitle('Home');

  const metrics = data?.metrics;
  const todayCount = data?.today.length ?? 0;

  return (
    <>
      <PageHeader
        eyebrow={new Date().toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' })}
        title={`${greeting()}, ${firstName}`}
        subtitle={
          data
            ? todayCount === 0
              ? 'There are no appointments on the schedule today.'
              : `You have ${pluralize(todayCount, 'appointment')} on the schedule today.`
            : 'Getting your day ready…'
        }
        actions={
          <>
            <ButtonLink to="/app/notes/new" icon="note">
              New note
            </ButtonLink>
            <ButtonLink to="/app/schedule/new" variant="primary" icon="calendar">
              Book appointment
            </ButtonLink>
          </>
        }
      />

      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch} skeleton={<SkeletonRows rows={8} />}>
        {data && metrics && (
          <div className="section-gap">
            {metrics.new_requests > 0 && (
              <Alert tone="info" icon="inbox" title={`${pluralize(metrics.new_requests, 'new request')} from the website`}>
                <Link to="/app/requests">Review them and reach out to each person</Link>
              </Alert>
            )}

            <div className="panel stats">
              <Link className="stat" to="/app/patients?status=active">
                <span className="stat__label">Patients in treatment</span>
                <span className="stat__value">{metrics.active_patients}</span>
                <span className="stat__hint">of {metrics.total_patients} registered</span>
              </Link>
              <Link className="stat" to="/app/notes">
                <span className="stat__label">Sessions this month</span>
                <span className="stat__value">{metrics.sessions_this_month}</span>
                <span className="stat__hint">notes recorded</span>
              </Link>
              <Link className="stat" to="/app/assessments?status=pending">
                <span className="stat__label">Pending questionnaires</span>
                <span className="stat__value">{metrics.pending_assessments}</span>
                <span className="stat__hint">assigned, not yet answered</span>
              </Link>
              <Link className="stat" to="/app/billing?status=issued">
                <span className="stat__label">Balance to collect</span>
                <span className="stat__value">{formatMoney(metrics.outstanding_balance, meta?.settings.currency)}</span>
                <span className="stat__hint">issued invoices</span>
              </Link>
            </div>

            <div className="layout-2">
              <div className="section-gap">
                <Panel
                  title="Today's schedule"
                  titleId="today"
                  flush
                  actions={
                    <ButtonLink to="/app/schedule" size="sm" variant="quiet" iconRight="chevronRight">
                      See the week
                    </ButtonLink>
                  }
                >
                  {data.today.length === 0 ? (
                    <EmptyState icon="calendar" title="A day with no appointments" text="A good moment to catch up on pending notes." />
                  ) : (
                    <ul className="list">
                      {data.today.map((appointment) => (
                        <AppointmentRow key={appointment.id} appointment={appointment} statuses={meta?.appointmentStatuses} />
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Upcoming appointments" titleId="upcoming" flush>
                  {data.upcoming.length === 0 ? (
                    <EmptyState icon="calendar" title="No upcoming appointments" />
                  ) : (
                    <ul className="list">
                      {data.upcoming.map((appointment) => (
                        <li key={appointment.id}>
                          <Link className="list__item" to={`/app/schedule/${appointment.id}/edit`}>
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

                <Panel title="Sessions per month" subtitle="Last six months" titleId="sessions">
                  <BarChart
                    label="Sessions recorded per month"
                    points={Object.entries(data.sessionsSeries).map(([period, value]) => ({ label: formatMonth(period), value }))}
                  />
                </Panel>
              </div>

              <div className="section-gap">
                <Panel title="Needs attention" subtitle="Moderate or high risk" titleId="risk" flush>
                  {data.riskPatients.length === 0 ? (
                    <EmptyState icon="shield" title="No risk alerts" text="No active patient has moderate or high risk." />
                  ) : (
                    <ul className="list">
                      {data.riskPatients.map((patient) => (
                        <li key={patient.id}>
                          <Link className="list__item" to={`/app/patients/${patient.id}`}>
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

                <Panel title="Questionnaires awaiting answers" titleId="pending" flush>
                  {data.pendingAssessments.length === 0 ? (
                    <EmptyState icon="clipboard" title="Nothing pending" />
                  ) : (
                    <ul className="list">
                      {data.pendingAssessments.map((item) => (
                        <li key={item.id}>
                          <Link className="list__item" to={`/app/patients/${item.patient_id}`}>
                            <span className="list__main">
                              <span className="list__title">{fullName(item)}</span>
                              <span className="list__meta">Assigned on {formatDate(item.created_at)}</span>
                            </span>
                            <Badge plain>{item.instrument_code}</Badge>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>

                <Panel title="Attendance" subtitle="Last 90 days" titleId="attendance">
                  <Distribution
                    label="Appointment status breakdown"
                    segments={Object.entries(data.attendance).map(([status, value]) => ({
                      label: labelOf(meta?.appointmentStatuses, status),
                      value,
                      tone: APPOINTMENT_STATUS_TONE[status] ?? 'neutral',
                    }))}
                  />
                </Panel>

                <Panel title="Recent notes" titleId="recent-notes" flush>
                  <ul className="list">
                    {data.recentNotes.map((note) => (
                      <li key={note.id}>
                        <Link className="list__item" to={`/app/notes/${note.id}`}>
                          <span className="list__main">
                            <span className="list__title">{fullName(note)}</span>
                            <span className="list__meta">
                              Session {note.session_number} · {formatDate(note.session_date)}
                            </span>
                          </span>
                          {note.is_locked ? <Badge tone="success">Signed</Badge> : <Badge tone="warning">Draft</Badge>}
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
