import { useState } from 'react';
import { Link } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { formatDate, formatTime, pretty } from '@/lib/format';
import { APPOINTMENT_STATUS_TONE, labelOf, RISK_TONE, severityTone, useMeta } from '@/lib/meta';
import { post } from '@/lib/api';
import { useAction } from '../../../useAction';
import type { PatientBundle } from './types';

export function NotesTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const id = bundle.patient.id;

  return (
    <Panel
      title="Notas de sesión"
      titleId="notas"
      flush
      actions={
        <ButtonLink to={`/app/notas/nueva?paciente=${id}`} size="sm" variant="primary" icon="plus">
          Nueva nota
        </ButtonLink>
      }
    >
      {bundle.notes.length === 0 ? (
        <EmptyState icon="note" title="Aún no hay notas" text="Registra la primera sesión para empezar la evolución." />
      ) : (
        <ul className="list">
          {bundle.notes.map((note) => (
            <li key={note.id}>
              <Link className="list__item" to={`/app/notas/${note.id}`}>
                <span className="list__main">
                  <span className="list__title">
                    Sesión {note.session_number} · {formatDate(note.session_date)}
                  </span>
                  <span className="list__meta">
                    {note.format.toUpperCase()} · {note.author_name}
                    {note.interventions && ` · ${note.interventions}`}
                  </span>
                </span>
                {note.risk_level !== 'none' && <Badge tone={RISK_TONE[note.risk_level]}>{labelOf(meta?.riskLevels, note.risk_level)}</Badge>}
                {note.is_locked ? <Badge tone="success">Firmada</Badge> : <Badge tone="warning">Borrador</Badge>}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

export function AssessmentsTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const id = bundle.patient.id;
  const [code, setCode] = useState('');
  const assign = useAction(() => post(`/api/patients/${id}/assessments`, { instrument_code: code }), {
    invalidate: [['patient', String(id)], ['assessments'], ['dashboard']],
    onSuccess: () => setCode(''),
  });

  return (
    <div className="layout-aside">
      <Panel
        title="Aplicaciones"
        titleId="aplicaciones"
        flush
        actions={
          <ButtonLink to={`/app/evaluaciones/nueva?paciente=${id}`} size="sm" icon="plus">
            Aplicar en consulta
          </ButtonLink>
        }
      >
        {bundle.assessments.length === 0 ? (
          <EmptyState icon="chart" title="Sin evaluaciones" text="Aplica un instrumento en consulta o envíalo al portal." />
        ) : (
          <ul className="list">
            {bundle.assessments.map((assessment) => (
              <li key={assessment.id}>
                <Link className="list__item" to={`/app/evaluaciones/${assessment.id}`}>
                  <span className="list__main">
                    <span className="list__title">{assessment.instrument_code}</span>
                    <span className="list__meta">
                      {assessment.status === 'completed'
                        ? `${formatDate(assessment.administered_at)} · ${assessment.total_score} puntos`
                        : `Asignado el ${formatDate(assessment.created_at)}`}
                    </span>
                  </span>
                  {assessment.status === 'completed' ? (
                    <Badge tone={severityTone(assessment.severity)}>{pretty(assessment.severity)}</Badge>
                  ) : (
                    <Badge tone="warning">Pendiente en el portal</Badge>
                  )}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <Panel title="Enviar al portal" subtitle="El paciente lo responderá desde casa" titleId="asignar">
        <div className="inline-form">
          <SelectField
            label="Instrumento"
            id="assign-instrument"
            placeholder="Elige uno"
            value={code}
            onChange={(event) => setCode(event.target.value)}
            options={meta?.instruments ?? []}
          />
          <div>
            <Button variant="primary" disabled={!code} loading={assign.pending} onClick={() => assign.run(undefined).catch(() => undefined)}>
              Asignar cuestionario
            </Button>
          </div>
        </div>
      </Panel>
    </div>
  );
}

export function AppointmentsTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();

  return (
    <Panel
      title="Historial de citas"
      titleId="citas"
      flush
      actions={
        <ButtonLink to={`/app/agenda/nueva?paciente=${bundle.patient.id}`} size="sm" variant="primary" icon="calendar">
          Agendar
        </ButtonLink>
      }
    >
      {bundle.appointments.length === 0 ? (
        <EmptyState icon="calendar" title="Sin citas registradas" />
      ) : (
        <div className="table-wrap">
          <table className="table table--stack">
            <thead>
              <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Modalidad</th>
                <th scope="col">Profesional</th>
                <th scope="col">Estado</th>
              </tr>
            </thead>
            <tbody>
              {bundle.appointments.map((appointment) => (
                <tr key={appointment.id}>
                  <td>
                    <Link className="table__link" to={`/app/agenda/${appointment.id}/editar`}>
                      {formatDate(appointment.starts_at)}
                    </Link>
                    <span className="table__sub">{formatTime(appointment.starts_at)}</span>
                  </td>
                  <td data-label="Modalidad">{labelOf(meta?.modalities, appointment.modality)}</td>
                  <td data-label="Profesional">{appointment.psychologist_name}</td>
                  <td data-label="Estado">
                    <Badge tone={APPOINTMENT_STATUS_TONE[appointment.status]}>{labelOf(meta?.appointmentStatuses, appointment.status)}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  );
}

