import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Avatar, Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { SelectField, TextField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { useDebounced } from '@/hooks/useDebounced';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { ageFrom, formatDate, fullName } from '@/lib/format';
import { labelOf, PATIENT_STATUS_TONE, RISK_TONE, useMeta } from '@/lib/meta';
import type { Page, PatientSummary } from '@/lib/types';

export default function PatientListPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const status = params.get('status') ?? '';
  const page = Number(params.get('page') ?? 1);
  const search = useDebounced(q, 300);
  useDocumentTitle('Patients');

  const query = useQuery({
    queryKey: ['patients', search, status, page],
    queryFn: () => get<Page<PatientSummary>>('/api/patients', { q: search, status, page }),
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
        title="Patients"
        subtitle={query.data ? `${query.data.total} ${query.data.total === 1 ? 'clinical record' : 'clinical records'}` : undefined}
        actions={
          <ButtonLink to="/app/patients/new" variant="primary" icon="plus">
            Add patient
          </ButtonLink>
        }
      />

      <div className="toolbar" role="search">
        <TextField
          wrapperClassName="toolbar__search"
          label="Search"
          id="patient-search"
          type="search"
          placeholder="Name, record number or ID"
          value={q}
          onChange={(event) => update('q', event.target.value)}
        />
        <SelectField
          label="Status"
          id="patient-status"
          value={status}
          onChange={(event) => update('status', event.target.value)}
          options={meta?.patientStatuses ?? []}
          placeholder="All"
        />
      </div>

      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState
              icon="users"
              title={q || status ? 'No patients match your search' : 'No patients yet'}
              text={q || status ? 'Try another name or clear the filters.' : 'Add your first clinical record to get started.'}
              action={
                !q && !status && (
                  <ButtonLink to="/app/patients/new" variant="primary" icon="plus" size="sm">
                    Add patient
                  </ButtonLink>
                )
              }
            />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Status</th>
                    <th scope="col">Risk</th>
                    <th scope="col">Professional</th>
                    <th scope="col">Last session</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((patient) => {
                    const age = ageFrom(patient.birth_date);
                    return (
                      <tr key={patient.id}>
                        <td>
                          <div className="cluster cluster--nowrap">
                            <Avatar name={fullName(patient)} size="sm" />
                            <div>
                              <Link className="table__link" to={`/app/patients/${patient.id}`}>
                                {patient.last_name}, {patient.first_name}
                              </Link>
                              <span className="table__sub">
                                {patient.record_number}
                                {age !== null && ` · ${age} years old`}
                              </span>
                            </div>
                          </div>
                        </td>
                        <td data-label="Status">
                          <Badge tone={PATIENT_STATUS_TONE[patient.status]}>{labelOf(meta?.patientStatuses, patient.status)}</Badge>
                        </td>
                        <td data-label="Risk">
                          <Badge tone={RISK_TONE[patient.risk_level]}>{labelOf(meta?.riskLevels, patient.risk_level)}</Badge>
                        </td>
                        <td data-label="Professional" className="soft">
                          {patient.psychologist_name ?? '—'}
                        </td>
                        <td data-label="Last session">
                          {formatDate(patient.last_session)}
                          <span className="table__sub">{patient.sessions_count === 1 ? '1 session' : `${patient.sessions_count} sessions`}</span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          {query.data && (
            <Pagination
              page={query.data.page}
              pages={query.data.pages}
              total={query.data.total}
              onChange={(next) => update('page', String(next))}
            />
          )}
        </QueryState>
      </Panel>
    </>
  );
}
