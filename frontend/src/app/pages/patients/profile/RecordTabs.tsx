import { useState } from 'react';
import { Link } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { formatDate, formatTime } from '@/lib/format';
import { APPOINTMENT_STATUS_TONE, labelOf, RISK_TONE, severityTone, useMeta } from '@/lib/meta';
import { post } from '@/lib/api';
import { useAction } from '../../../useAction';
import type { PatientBundle } from './types';

export function NotesTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const id = bundle.patient.id;

  return (
    <Panel
      title="Session notes"
      titleId="notes"
      flush
      actions={
        <ButtonLink to={`/app/notes/new?patient=${id}`} size="sm" variant="primary" icon="plus">
          New note
        </ButtonLink>
      }
    >
      {bundle.notes.length === 0 ? (
        <EmptyState icon="note" title="No notes yet" text="Record the first session to start tracking progress." />
      ) : (
        <ul className="list">
          {bundle.notes.map((note) => (
            <li key={note.id}>
              <Link className="list__item" to={`/app/notes/${note.id}`}>
                <span className="list__main">
                  <span className="list__title">
                    Session {note.session_number} · {formatDate(note.session_date)}
                  </span>
                  <span className="list__meta">
                    {note.format.toUpperCase()} · {note.author_name}
                    {note.interventions && ` · ${note.interventions}`}
                  </span>
                </span>
                {note.risk_level !== 'none' && <Badge tone={RISK_TONE[note.risk_level]}>{labelOf(meta?.riskLevels, note.risk_level)}</Badge>}
                {note.is_locked ? <Badge tone="success">Signed</Badge> : <Badge tone="warning">Draft</Badge>}
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
        title="Assessments"
        titleId="assessments"
        flush
        actions={
          <ButtonLink to={`/app/assessments/new?patient=${id}`} size="sm" icon="plus">
            Administer in session
          </ButtonLink>
        }
      >
        {bundle.assessments.length === 0 ? (
          <EmptyState icon="chart" title="No assessments yet" text="Administer an instrument in session or send it to the portal." />
        ) : (
          <ul className="list">
            {bundle.assessments.map((assessment) => (
              <li key={assessment.id}>
                <Link className="list__item" to={`/app/assessments/${assessment.id}`}>
                  <span className="list__main">
                    <span className="list__title">{assessment.instrument_code}</span>
                    <span className="list__meta">
                      {assessment.status === 'completed'
                        ? `${formatDate(assessment.administered_at)} · ${assessment.total_score} points`
                        : `Assigned on ${formatDate(assessment.created_at)}`}
                    </span>
                  </span>
                  {assessment.status === 'completed' ? (
                    <Badge tone={severityTone(assessment.severity)}>{assessment.severity}</Badge>
                  ) : (
                    <Badge tone="warning">Pending in the portal</Badge>
                  )}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <Panel title="Send to the portal" subtitle="The patient will answer it from home" titleId="assign">
        <div className="inline-form">
          <SelectField
            label="Instrument"
            id="assign-instrument"
            placeholder="Choose one"
            value={code}
            onChange={(event) => setCode(event.target.value)}
            options={meta?.instruments ?? []}
          />
          <div>
            <Button variant="primary" disabled={!code} loading={assign.pending} onClick={() => assign.run(undefined).catch(() => undefined)}>
              Assign questionnaire
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
      title="Appointment history"
      titleId="appointments"
      flush
      actions={
        <ButtonLink to={`/app/schedule/new?patient=${bundle.patient.id}`} size="sm" variant="primary" icon="calendar">
          Book
        </ButtonLink>
      }
    >
      {bundle.appointments.length === 0 ? (
        <EmptyState icon="calendar" title="No appointments yet" />
      ) : (
        <div className="table-wrap">
          <table className="table table--stack">
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Modality</th>
                <th scope="col">Professional</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              {bundle.appointments.map((appointment) => (
                <tr key={appointment.id}>
                  <td>
                    <Link className="table__link" to={`/app/schedule/${appointment.id}/edit`}>
                      {formatDate(appointment.starts_at)}
                    </Link>
                    <span className="table__sub">{formatTime(appointment.starts_at)}</span>
                  </td>
                  <td data-label="Modality">{labelOf(meta?.modalities, appointment.modality)}</td>
                  <td data-label="Professional">{appointment.psychologist_name}</td>
                  <td data-label="Status">
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

