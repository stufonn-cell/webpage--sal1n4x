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
    <section className="next-session enter" aria-labelledby="proxima">
      <p className="eyebrow" id="proxima">
        Tu próxima sesión
      </p>
      <p className="next-session__date serif">
        {relativeDay(appointment.starts_at)}, {formatTime(appointment.starts_at)}
      </p>
      <p className="soft">
        {formatLongDate(appointment.starts_at)} · {MODALITY_LABELS[appointment.modality] ?? appointment.modality} con {appointment.psychologist_name}
      </p>
      {online && appointment.meeting_url ? (
        <a className="btn btn--primary" href={appointment.meeting_url} target="_blank" rel="noopener noreferrer">
          <Icon name="video" size={17} />
          <span className="btn__label">Entrar a la videollamada</span>
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
  useDocumentTitle('Inicio');
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
                ? 'No tienes nada pendiente. Este es tu espacio para consultar tus citas y documentos cuando lo necesites.'
                : `Tienes ${tasks === 1 ? 'una cosa' : `${tasks} cosas`} por revisar. Sin prisa: puedes hacerlo cuando quieras.`}
            </p>
          </header>

          {data.appointments[0] ? (
            <NextAppointment appointment={data.appointments[0]} />
          ) : (
            <Panel>
              <EmptyState icon="calendar" title="No tienes citas próximas" text="Cuando tu profesional agende una sesión aparecerá aquí." />
            </Panel>
          )}

          {tasks > 0 && (
            <Panel title="Por revisar" titleId="pendientes" flush>
              <ul className="list">
                {data.pending.map((item) => (
                  <li key={`a-${item.id}`}>
                    <Link className="list__item" to={`/portal/cuestionarios/${item.id}`}>
                      <Icon name="clipboard" size={18} />
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">
                          Cuestionario de {item.items_count} preguntas · unos {Math.max(2, Math.round(item.items_count / 3))} minutos
                        </span>
                      </span>
                      <Icon name="chevronRight" size={18} />
                    </Link>
                  </li>
                ))}
                {pendingConsents.map((consent) => (
                  <li key={`c-${consent.id}`}>
                    <Link className="list__item" to={`/portal/consentimientos/${consent.id}`}>
                      <Icon name="pen" size={18} />
                      <span className="list__main">
                        <span className="list__title">{consent.title}</span>
                        <span className="list__meta">Documento para leer y firmar</span>
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
              title="Próximas citas"
              titleId="citas"
              flush
              actions={
                <ButtonLink to="/portal/citas" size="sm" variant="quiet" iconRight="chevronRight">
                  Ver todas
                </ButtonLink>
              }
            >
              {data.appointments.length === 0 ? (
                <EmptyState icon="calendar" title="Sin citas agendadas" />
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

            <Panel title="Lo que ya completaste" titleId="completado" flush>
              {data.completed.length === 0 && data.consents.every((consent) => consent.status !== 'signed') ? (
                <EmptyState icon="checkCircle" title="Aquí verás lo que vayas completando" />
              ) : (
                <ul className="list">
                  {data.completed.slice(0, 4).map((item) => (
                    <li key={item.id} className="list__item">
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">Respondido el {formatDate(item.administered_at)}</span>
                      </span>
                      <Badge tone="success">Enviado</Badge>
                    </li>
                  ))}
                  {data.consents
                    .filter((consent) => consent.status === 'signed')
                    .map((consent) => (
                      <li key={consent.id}>
                        <Link className="list__item" to={`/portal/consentimientos/${consent.id}`}>
                          <span className="list__main">
                            <span className="list__title">{consent.title}</span>
                            <span className="list__meta">Firmado el {formatDate(consent.signed_at)}</span>
                          </span>
                          <Badge tone="success">Firmado</Badge>
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
