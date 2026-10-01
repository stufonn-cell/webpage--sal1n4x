import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, fullName, pretty } from '@/lib/format';
import { severityTone, useMeta } from '@/lib/meta';
import type { Assessment, Page } from '@/lib/types';

const STATUS_OPTIONS = [
  { value: 'completed', label: 'Respondidas' },
  { value: 'pending', label: 'Pendientes' },
];

export default function AssessmentListPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const instrument = params.get('instrumento') ?? '';
  const status = params.get('status') ?? '';
  const page = Number(params.get('page') ?? 1);
  useDocumentTitle('Evaluaciones');

  const query = useQuery({
    queryKey: ['assessments', instrument, status, page],
    queryFn: () => get<Page<Assessment>>('/api/assessments', { instrument, status, page }),
    placeholderData: keepPreviousData,
  });

  const update = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value);
    else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next, { replace: true });
  };

  return (
    <>
      <PageHeader
        title="Evaluaciones psicométricas"
        subtitle="Corrección automática con bandas de severidad. Los puntajes orientan y no sustituyen el juicio clínico."
        actions={
          <>
            <ButtonLink to="/app/evaluaciones/catalogo" icon="clipboard">
              Catálogo
            </ButtonLink>
            <ButtonLink to="/app/evaluaciones/nueva" variant="primary" icon="plus">
              Aplicar instrumento
            </ButtonLink>
          </>
        }
      />
      <div className="toolbar">
        <SelectField
          label="Instrumento"
          id="filter-instrument"
          value={instrument}
          onChange={(event) => update('instrumento', event.target.value)}
          options={meta?.instruments ?? []}
          placeholder="Todos"
        />
        <SelectField label="Estado" id="filter-status" value={status} onChange={(event) => update('status', event.target.value)} options={STATUS_OPTIONS} placeholder="Todas" />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState icon="chart" title="No hay evaluaciones con estos filtros" />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Paciente</th>
                    <th scope="col">Instrumento</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" className="num">
                      Puntaje
                    </th>
                    <th scope="col">Resultado</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((row) => (
                    <tr key={row.id}>
                      <td>
                        <Link className="table__link" to={row.status === 'completed' ? `/app/evaluaciones/${row.id}` : `/app/pacientes/${row.patient_id}?tab=evaluaciones`}>
                          {fullName(row)}
                        </Link>
                        <span className="table__sub">{row.record_number}</span>
                      </td>
                      <td data-label="Instrumento">{row.instrument_code}</td>
                      <td data-label="Fecha">{formatDate(row.administered_at ?? row.created_at)}</td>
                      <td data-label="Puntaje" className="num">
                        {row.total_score ?? '—'}
                      </td>
                      <td data-label="Resultado">
                        {row.status === 'completed' ? (
                          <Badge tone={severityTone(row.severity)}>{pretty(row.severity)}</Badge>
                        ) : (
                          <Badge tone="warning">Pendiente</Badge>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {query.data && (
            <Pagination page={query.data.page} pages={query.data.pages} total={query.data.total} onChange={(next) => update('page', String(next))} />
          )}
        </QueryState>
      </Panel>
    </>
  );
}
