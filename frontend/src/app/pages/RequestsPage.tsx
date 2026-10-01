import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get, patch } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { labelOf, REQUEST_STATUS_TONE, useMeta } from '@/lib/meta';
import type { AppointmentRequest, Page } from '@/lib/types';
import { useAction } from '../useAction';

/** Solicitudes que llegan desde el formulario publico del sitio. */
export default function RequestsPage() {
  const { data: meta } = useMeta();
  const [status, setStatus] = useState('new');
  const [page, setPage] = useState(1);
  useDocumentTitle('Solicitudes de cita');

  const query = useQuery({
    queryKey: ['requests', status, page],
    queryFn: () => get<Page<AppointmentRequest>>('/api/appointment-requests', { status, page }),
    placeholderData: keepPreviousData,
  });

  const update = useAction(
    ({ id, next }: { id: number; next: string }) => patch(`/api/appointment-requests/${id}`, { status: next }),
    { invalidate: [['requests'], ['dashboard']] },
  );

  const times = (value: string | null) =>
    value
      ? value
          .split(',')
          .map((time) => labelOf(meta?.requestTimes, time))
          .join(', ')
      : 'Sin preferencia';

  return (
    <>
      <PageHeader
        title="Solicitudes de cita"
        subtitle="Personas que escribieron desde el sitio. Contáctalas por el medio que eligieron y, si corresponde, crea su ficha y agenda la cita."
      />

      <div className="toolbar">
        <SelectField
          label="Estado"
          id="status-filter"
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
          options={meta?.requestStatuses ?? []}
          placeholder="Todas"
        />
      </div>

      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data && query.data.rows.length === 0 ? (
            <EmptyState
              icon="inbox"
              title={status === 'new' ? 'No hay solicitudes nuevas' : 'No hay solicitudes con este estado'}
              text="Cuando alguien pida una cita desde el sitio aparecerá aquí."
            />
          ) : (
            <ul className="list">
              {query.data?.rows.map((row) => (
                <li key={row.id} className="list__item request-row">
                  <div className="list__main stack stack--tight">
                    <div className="cluster">
                      <strong>{row.full_name}</strong>
                      <Badge tone={REQUEST_STATUS_TONE[row.status]}>{labelOf(meta?.requestStatuses, row.status)}</Badge>
                      <span className="xsmall muted">{formatDateTime(row.created_at)}</span>
                    </div>
                    <div className="cluster small soft">
                      <span>{labelOf(meta?.requestAttendees, row.attendee)}</span>
                      <span>· {labelOf(meta?.requestModalities, row.modality)}</span>
                      <span>· {times(row.preferred_times)}</span>
                      {row.professional_name && <span>· Prefiere a {row.professional_name}</span>}
                    </div>
                    {row.message && <p className="small prewrap">“{row.message}”</p>}
                    <div className="cluster small">
                      <a href={`mailto:${row.email}`}>
                        <Icon name="mail" size={14} /> {row.email}
                      </a>
                      {row.phone && (
                        <a href={`tel:${row.phone.replace(/[^\d+]/g, '')}`}>
                          <Icon name="phone" size={14} /> {row.phone}
                        </a>
                      )}
                      <span className="muted">Prefiere: {labelOf(meta?.requestContact, row.contact_preference)}</span>
                    </div>
                    {row.handled_by_name && (
                      <span className="xsmall muted">
                        Gestionada por {row.handled_by_name} · {formatDateTime(row.handled_at)}
                      </span>
                    )}
                  </div>
                  <div className="cluster request-row__actions">
                    {row.status === 'new' && (
                      <Button size="sm" onClick={() => update.run({ id: row.id, next: 'contacted' })} disabled={update.pending}>
                        Marcar contactada
                      </Button>
                    )}
                    {row.status !== 'scheduled' && row.status !== 'dismissed' && (
                      <ButtonLink
                        size="sm"
                        variant="primary"
                        to="/app/pacientes/nuevo"
                        // Los datos viajan en el estado del router, nunca en la URL.
                        state={{ prefill: { full_name: row.full_name, email: row.email, phone: row.phone ?? '' }, requestId: row.id }}
                      >
                        Crear ficha
                      </ButtonLink>
                    )}
                    {row.status !== 'scheduled' && (
                      <Button size="sm" variant="quiet" onClick={() => update.run({ id: row.id, next: 'scheduled' })} disabled={update.pending}>
                        Cita agendada
                      </Button>
                    )}
                    {row.status !== 'dismissed' && (
                      <Button size="sm" variant="quiet" onClick={() => update.run({ id: row.id, next: 'dismissed' })} disabled={update.pending}>
                        Descartar
                      </Button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
          {query.data && <Pagination page={query.data.page} pages={query.data.pages} total={query.data.total} onChange={setPage} />}
        </QueryState>
      </Panel>
    </>
  );
}
