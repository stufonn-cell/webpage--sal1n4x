import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, fullName } from '@/lib/format';
import { severityTone, useMeta } from '@/lib/meta';
import type { Assessment, Page } from '@/lib/types';

const STATUS_OPTIONS = [
  { value: 'completed', label: 'Completed' },
  { value: 'pending', label: 'Pending' },
];

export default function AssessmentListPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const instrument = params.get('instrument') ?? '';
  const status = params.get('status') ?? '';
  const page = Number(params.get('page') ?? 1);
  useDocumentTitle('Assessments');

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
        title="Psychometric assessments"
        subtitle="Automatic scoring with severity bands. Scores guide you; they don't replace clinical judgment."
        actions={
          <>
            <ButtonLink to="/app/assessments/catalog" icon="clipboard">
              Catalog
            </ButtonLink>
            <ButtonLink to="/app/assessments/new" variant="primary" icon="plus">
              Administer instrument
            </ButtonLink>
          </>
        }
      />
      <div className="toolbar">
        <SelectField
          label="Instrument"
          id="filter-instrument"
          value={instrument}
          onChange={(event) => update('instrument', event.target.value)}
          options={meta?.instruments ?? []}
          placeholder="All"
        />
        <SelectField label="Status" id="filter-status" value={status} onChange={(event) => update('status', event.target.value)} options={STATUS_OPTIONS} placeholder="All" />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState icon="chart" title="No assessments match these filters" />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Instrument</th>
                    <th scope="col">Date</th>
                    <th scope="col" className="num">
                      Score
                    </th>
                    <th scope="col">Result</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((row) => (
                    <tr key={row.id}>
                      <td>
                        <Link className="table__link" to={row.status === 'completed' ? `/app/assessments/${row.id}` : `/app/patients/${row.patient_id}?tab=assessments`}>
                          {fullName(row)}
                        </Link>
                        <span className="table__sub">{row.record_number}</span>
                      </td>
                      <td data-label="Instrument">{row.instrument_code}</td>
                      <td data-label="Date">{formatDate(row.administered_at ?? row.created_at)}</td>
                      <td data-label="Score" className="num">
                        {row.total_score ?? '—'}
                      </td>
                      <td data-label="Result">
                        {row.status === 'completed' ? (
                          <Badge tone={severityTone(row.severity)}>{row.severity}</Badge>
                        ) : (
                          <Badge tone="warning">Pending</Badge>
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
