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
  login: 'Inició sesión',
  logout: 'Cerró sesión',
  view: 'Consultó',
  create: 'Creó',
  update: 'Modificó',
  delete: 'Eliminó',
  sign: 'Firmó',
  upload: 'Subió',
  download: 'Descargó',
  payment: 'Registró un pago en',
  assign: 'Asignó',
  submit: 'Respondió',
  toggle: 'Cambió el estado de',
};

const ENTITIES: Record<string, string> = {
  user: 'usuario',
  patient: 'paciente',
  appointment: 'cita',
  clinical_note: 'nota clínica',
  assessment: 'evaluación',
  consent: 'consentimiento',
  document: 'documento',
  invoice: 'factura',
  settings: 'configuración',
  diagnosis: 'diagnóstico',
  appointment_request: 'solicitud de cita',
};

function describe(entry: AuditEntry): string {
  const [action, detail] = entry.action.split(':');
  const verb = ACTIONS[action] ?? entry.action;
  const entity = ENTITIES[entry.entity] ?? entry.entity;
  const suffix = detail ? ` (${detail.replace('_', ' ')})` : '';
  return `${verb} ${entity}${entry.entity_id ? ` #${entry.entity_id}` : ''}${suffix}`;
}

export default function AuditPage() {
  useDocumentTitle('Auditoría');
  const [filter, setFilter] = useState('');
  const query = useQuery({ queryKey: ['audit'], queryFn: () => get<AuditEntry[]>('/api/audit') });

  const rows = useMemo(() => {
    const term = filter.trim().toLowerCase();
    if (!term) return query.data ?? [];
    return (query.data ?? []).filter((entry) => `${entry.full_name ?? ''} ${describe(entry)}`.toLowerCase().includes(term));
  }, [filter, query.data]);

  return (
    <>
      <PageHeader title="Registro de auditoría" subtitle="Las últimas 150 acciones sobre la historia clínica. Este registro no se puede modificar desde la aplicación." />
      <div className="toolbar" role="search">
        <TextField wrapperClassName="toolbar__search" label="Filtrar" id="audit-filter" type="search" placeholder="Persona o acción" value={filter} onChange={(event) => setFilter(event.target.value)} />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          <div className="table-wrap">
            <table className="table table--stack">
              <thead>
                <tr>
                  <th scope="col">Fecha</th>
                  <th scope="col">Persona</th>
                  <th scope="col">Acción</th>
                  <th scope="col">IP</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((entry) => (
                  <tr key={entry.id}>
                    <td className="tabular">{formatDateTime(entry.created_at)}</td>
                    <td data-label="Persona">{entry.full_name ?? 'Visitante del sitio'}</td>
                    <td data-label="Acción">{describe(entry)}</td>
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
