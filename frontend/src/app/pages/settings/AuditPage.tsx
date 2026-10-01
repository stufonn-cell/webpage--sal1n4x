import { useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { PageHeader, Panel } from '@/components/ui/Display';
import { TextField } from '@/components/ui/Field';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import type { AuditEntry } from '@/lib/types';

const ACTIONS: Record<string, string> = {
  login: 'Signed in as',
  logout: 'Signed out as',
  view: 'Viewed',
  create: 'Created',
  update: 'Updated',
  delete: 'Deleted',
  sign: 'Signed',
  upload: 'Uploaded',
  download: 'Downloaded',
  payment: 'Recorded a payment on',
  assign: 'Assigned',
  submit: 'Submitted',
  toggle: 'Changed the status of',
  status: 'Changed the status of',
  send: 'Sent',
};

const ENTITIES: Record<string, string> = {
  user: 'user',
  patient: 'patient',
  appointment: 'appointment',
  clinical_note: 'session note',
  assessment: 'assessment',
  consent: 'consent',
  document: 'document',
  invoice: 'invoice',
  settings: 'settings',
  diagnosis: 'diagnosis',
  appointment_request: 'appointment request',
  rips_report: 'RIPS report',
};

function describe(entry: AuditEntry): string {
  const [action, detail] = entry.action.split(':');
  const verb = ACTIONS[action] ?? entry.action;
  const entity = ENTITIES[entry.entity] ?? entry.entity;
  const suffix = detail ? ` (${detail.replace('_', ' ')})` : '';
  return `${verb} ${entity}${entry.entity_id ? ` #${entry.entity_id}` : ''}${suffix}`;
}

export default function AuditPage() {
  useDocumentTitle('Audit log');
  const [filter, setFilter] = useState('');
  const query = useQuery({ queryKey: ['audit'], queryFn: () => get<AuditEntry[]>('/api/audit') });

  const rows = useMemo(() => {
    const term = filter.trim().toLowerCase();
    if (!term) return query.data ?? [];
    return (query.data ?? []).filter((entry) => `${entry.full_name ?? ''} ${describe(entry)}`.toLowerCase().includes(term));
  }, [filter, query.data]);

  return (
    <>
      <PageHeader title="Audit log" subtitle="The latest 150 actions on clinical records. This log can't be changed from the app." />
      <div className="toolbar" role="search">
        <TextField wrapperClassName="toolbar__search" label="Filter" id="audit-filter" type="search" placeholder="Person or action" value={filter} onChange={(event) => setFilter(event.target.value)} />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          <div className="table-wrap">
            <table className="table table--stack">
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Person</th>
                  <th scope="col">Action</th>
                  <th scope="col">IP</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((entry) => (
                  <tr key={entry.id}>
                    <td className="tabular">{formatDateTime(entry.created_at)}</td>
                    <td data-label="Person">{entry.full_name ?? 'Website visitor'}</td>
                    <td data-label="Action">{describe(entry)}</td>
                    <td data-label="IP" className="muted tabular">
                      {entry.ip_address ?? '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </QueryState>
      </Panel>
    </>
  );
}
