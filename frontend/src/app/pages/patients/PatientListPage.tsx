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
  useDocumentTitle('Pacientes');

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
        title="Pacientes"
        subtitle={query.data ? `${query.data.total} historias clínicas` : undefined}
        actions={
          <ButtonLink to="/app/pacientes/nuevo" variant="primary" icon="plus">
            Registrar paciente
          </ButtonLink>
        }
      />

      <div className="toolbar" role="search">
        <TextField
          wrapperClassName="toolbar__search"
          label="Buscar"
          id="patient-search"
          type="search"
          placeholder="Nombre, historia o documento"
          value={q}
          onChange={(event) => update('q', event.target.value)}
        />
        <SelectField
          label="Estado"
          id="patient-status"
          value={status}
          onChange={(event) => update('status', event.target.value)}
          options={meta?.patientStatuses ?? []}
          placeholder="Todos"
        />
      </div>

      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState
              icon="users"
              title={q || status ? 'Ningún paciente coincide con la búsqueda' : 'Aún no hay pacientes'}
              text={q || status ? 'Prueba con otro nombre o quita los filtros.' : 'Registra la primera historia clínica para empezar.'}
              action={
                !q && !status && (
                  <ButtonLink to="/app/pacientes/nuevo" variant="primary" icon="plus" size="sm">
                    Registrar paciente
                  </ButtonLink>
                )
              }
            />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Paciente</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Riesgo</th>
                    <th scope="col">Profesional</th>
                    <th scope="col">Última sesión</th>
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
                              <Link className="table__link" to={`/app/pacientes/${patient.id}`}>
                                {patient.last_name}, {patient.first_name}
                              </Link>
                              <span className="table__sub">
                                {patient.record_number}
                                {age !== null && ` · ${age} años`}
                              </span>
                            </div>
                          </div>
                        </td>
                        <td data-label="Estado">
                          <Badge tone={PATIENT_STATUS_TONE[patient.status]}>{labelOf(meta?.patientStatuses, patient.status)}</Badge>
                        </td>
                        <td data-label="Riesgo">
                          <Badge tone={RISK_TONE[patient.risk_level]}>{labelOf(meta?.riskLevels, patient.risk_level)}</Badge>
                        </td>
                        <td data-label="Profesional" className="soft">
                          {patient.psychologist_name ?? '—'}
                        </td>
                        <td data-label="Última sesión">
                          {formatDate(patient.last_session)}
                          <span className="table__sub">{patient.sessions_count} sesiones</span>
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
