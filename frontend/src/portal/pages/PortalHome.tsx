import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { QueryState, SkeletonRows } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, formatLongDate, formatTime, greeting, relativeDay } from '@/lib/format';
import { MODALITY_LABELS, type PortalAppointment, type PortalHomeData } from '../types';

function NextAppointment({ appointment }: { appointment: PortalAppointment }) {
  const online = appointment.modality !== 'in_person';
  return (
    <section className="next-session enter" aria-labelledby="next-session-title">
      <p className="eyebrow" id="next-session-title">
        Your next session
      </p>
      <p className="next-session__date serif">
        {relativeDay(appointment.starts_at)}, {formatTime(appointment.starts_at)}
      </p>
      <p className="soft">
        {formatLongDate(appointment.starts_at)} · {MODALITY_LABELS[appointment.modality] ?? appointment.modality} with {appointment.psychologist_name}
      </p>
      {online && appointment.meeting_url ? (
        <a className="btn btn--primary" href={appointment.meeting_url} target="_blank" rel="noopener noreferrer">
          <Icon name="video" size={17} />
          <span className="btn__label">Join the video call</span>
        </a>
      ) : (
        appointment.location && (
          <p className="small">
            <Icon name="mapPin" size={15} className="inline-icon" /> {appointment.location}
          </p>
        )
      )}
    </section>
  );
}

export default function PortalHome() {
  useDocumentTitle('Home');
  const query = useQuery({ queryKey: ['portal', 'home'], queryFn: () => get<PortalHomeData>('/api/portal') });
  const data = query.data;
  const pendingConsents = data?.consents.filter((consent) => consent.status === 'pending') ?? [];
  const tasks = (data?.pending.length ?? 0) + pendingConsents.length;

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch} skeleton={<SkeletonRows rows={6} />}>
      {data && (
        <div className="section-gap">
          <header className="portal-hello enter">
            <h1 className="serif">
              {greeting()}, {data.patient.first_name}
            </h1>
            <p className="soft">
              {tasks === 0
                ? 'You have nothing pending. This is your space to check your appointments and documents whenever you need to.'
                : `You have ${tasks === 1 ? 'one thing' : `${tasks} things`} to review. No rush: you can do it whenever you like.`}
            </p>
          </header>

          {data.appointments[0] ? (
            <NextAppointment appointment={data.appointments[0]} />
          ) : (
            <Panel>
              <EmptyState icon="calendar" title="You have no upcoming appointments" text="When your professional schedules a session, it will show up here." />
            </Panel>
          )}

          {tasks > 0 && (
            <Panel title="To review" titleId="to-review" flush>
              <ul className="list">
                {data.pending.map((item) => (
                  <li key={`a-${item.id}`}>
                    <Link className="list__item" to={`/portal/questionnaires/${item.id}`}>
                      <Icon name="clipboard" size={18} />
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">
                          {item.items_count}-question questionnaire · about {Math.max(2, Math.round(item.items_count / 3))} minutes
                        </span>
                      </span>
                      <Icon name="chevronRight" size={18} />
                    </Link>
                  </li>
                ))}
                {pendingConsents.map((consent) => (
                  <li key={`c-${consent.id}`}>
                    <Link className="list__item" to={`/portal/consents/${consent.id}`}>
                      <Icon name="pen" size={18} />
                      <span className="list__main">
                        <span className="list__title">{consent.title}</span>
                        <span className="list__meta">Document to read and sign</span>
                      </span>
                      <Icon name="chevronRight" size={18} />
                    </Link>
                  </li>
                ))}
              </ul>
            </Panel>
          )}

          <div className="grid-2">
            <Panel
              title="Upcoming appointments"
              titleId="appointments"
              flush
              actions={
                <ButtonLink to="/portal/appointments" size="sm" variant="quiet" iconRight="chevronRight">
                  See all
                </ButtonLink>
              }
            >
              {data.appointments.length === 0 ? (
                <EmptyState icon="calendar" title="No appointments scheduled" />
              ) : (
                <ul className="list">
                  {data.appointments.map((appointment) => (
                    <li key={appointment.id} className="list__item">
                      <span className="list__main">
                        <span className="list__title">
                          {formatDate(appointment.starts_at, { weekday: 'long', day: 'numeric', month: 'long' })}
                        </span>
                        <span className="list__meta">
                          {formatTime(appointment.starts_at)} · {MODALITY_LABELS[appointment.modality]}
                        </span>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>

            <Panel title="What you have completed" titleId="completed" flush>
              {data.completed.length === 0 && data.consents.every((consent) => consent.status !== 'signed') ? (
                <EmptyState icon="checkCircle" title="What you complete will show up here" />
              ) : (
                <ul className="list">
                  {data.completed.slice(0, 4).map((item) => (
                    <li key={item.id} className="list__item">
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">Answered on {formatDate(item.administered_at)}</span>
                      </span>
                      <Badge tone="success">Submitted</Badge>
                    </li>
                  ))}
                  {data.consents
                    .filter((consent) => consent.status === 'signed')
                    .map((consent) => (
                      <li key={consent.id}>
                        <Link className="list__item" to={`/portal/consents/${consent.id}`}>
                          <span className="list__main">
                            <span className="list__title">{consent.title}</span>
                            <span className="list__meta">Signed on {formatDate(consent.signed_at)}</span>
                          </span>
                          <Badge tone="success">Signed</Badge>
                        </Link>
                      </li>
                    ))}
                </ul>
              )}
            </Panel>
          </div>
        </div>
      )}
    </QueryState>
  );
}
