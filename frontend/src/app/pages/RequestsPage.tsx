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

/** Requests that come in from the public website form. */
export default function RequestsPage() {
  const { data: meta } = useMeta();
  const [status, setStatus] = useState('new');
  const [page, setPage] = useState(1);
  useDocumentTitle('Appointment requests');

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
      : 'No preference';

  return (
    <>
      <PageHeader
        title="Appointment requests"
        subtitle="People who reached out through the website. Contact them the way they chose and, when it makes sense, create their file and book the appointment."
      />

      <div className="toolbar">
        <SelectField
          label="Status"
          id="status-filter"
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
          options={meta?.requestStatuses ?? []}
          placeholder="All"
        />
      </div>

      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data && query.data.rows.length === 0 ? (
            <EmptyState
              icon="inbox"
              title={status === 'new' ? 'No new requests' : 'No requests with this status'}
              text="When someone asks for an appointment through the website, it will show up here."
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
                      {row.professional_name && <span>· Prefers {row.professional_name}</span>}
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
                      <span className="muted">Prefers: {labelOf(meta?.requestContact, row.contact_preference)}</span>
                    </div>
                    {row.handled_by_name && (
                      <span className="xsmall muted">
                        Handled by {row.handled_by_name} · {formatDateTime(row.handled_at)}
                      </span>
                    )}
                  </div>
                  <div className="cluster request-row__actions">
                    {row.status === 'new' && (
                      <Button size="sm" onClick={() => update.run({ id: row.id, next: 'contacted' })} disabled={update.pending}>
                        Mark as contacted
                      </Button>
                    )}
                    {row.status !== 'scheduled' && row.status !== 'dismissed' && (
                      <ButtonLink
                        size="sm"
                        variant="primary"
                        to="/app/patients/new"
                        // The details travel in router state, never in the URL.
                        state={{ prefill: { full_name: row.full_name, email: row.email, phone: row.phone ?? '' }, requestId: row.id }}
                      >
                        Create patient file
                      </ButtonLink>
                    )}
                    {row.status !== 'scheduled' && (
                      <Button size="sm" variant="quiet" onClick={() => update.run({ id: row.id, next: 'scheduled' })} disabled={update.pending}>
                        Appointment booked
                      </Button>
                    )}
                    {row.status !== 'dismissed' && (
                      <Button size="sm" variant="quiet" onClick={() => update.run({ id: row.id, next: 'dismissed' })} disabled={update.pending}>
                        Dismiss
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
