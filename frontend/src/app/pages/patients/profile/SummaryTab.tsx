import { useState } from 'react';
import { Link } from 'react-router';
import { LineChart } from '@/components/charts/Charts';
import { Button } from '@/components/ui/Button';
import { Alert, EmptyState, Facts, Panel } from '@/components/ui/Display';
import { useConfirm } from '@/components/ui/Feedback';
import { post } from '@/lib/api';
import { ageFrom, formatDate, formatDateTime } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import { useAction } from '../../../useAction';
import type { PatientBundle } from './types';

function PortalAccess({ bundle }: { bundle: PatientBundle }) {
  const confirm = useConfirm();
  const [credentials, setCredentials] = useState<{ username: string; temporaryPassword: string } | null>(null);
  const { patient, portalAccount } = bundle;

  const create = useAction(() => post<{ username: string; temporaryPassword: string }>(`/api/patients/${patient.id}/portal-access`), {
    invalidate: [['patient', String(patient.id)]],
    onSuccess: (result) => setCredentials(result.data),
  });

  const onCreate = async () => {
    const ok = await confirm({
      title: 'Crear acceso al portal',
      text: `Se creará una cuenta para ${patient.first_name} con una contraseña temporal. Te la mostraremos una sola vez para que se la entregues en persona.`,
      confirmLabel: 'Crear acceso',
    });
    if (ok) await create.run(undefined).catch(() => undefined);
  };

  return (
    <Panel title="Portal del paciente" titleId="portal">
      {credentials ? (
        <div className="stack">
          <Alert tone="success" title="Acceso creado">
            Entrega estos datos en persona. Por seguridad no volverán a mostrarse.
          </Alert>
          <div className="secret-box">
            <span>Usuario: {credentials.username}</span>
            <span>Contraseña temporal: {credentials.temporaryPassword}</span>
          </div>
        </div>
      ) : portalAccount ? (
        <Facts
          items={[
            ['Usuario', portalAccount.username],
            ['Estado', portalAccount.is_active ? 'Activo' : 'Desactivado'],
            ['Último ingreso', formatDateTime(portalAccount.last_login_at)],
          ]}
        />
      ) : (
        <div className="stack">
          <p className="small soft">
            Con el portal, {patient.first_name} puede ver sus citas, responder cuestionarios y firmar consentimientos desde casa.
          </p>
          {!patient.email && <p className="small muted">Primero registra un correo en la ficha.</p>}
          <div>
            <Button size="sm" icon="user" onClick={onCreate} disabled={!patient.email} loading={create.pending}>
              Crear acceso
            </Button>
          </div>
        </div>
      )}
    </Panel>
  );
}

export function SummaryTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const { patient } = bundle;
  const age = ageFrom(patient.birth_date);

  return (
    <div className="layout-2">
      <div className="section-gap">
        <Panel title="Ficha clínica" titleId="ficha">
          <div className="section-gap">
            {patient.reason_for_consult && (
              <div className="prose-block">
                <h3>Motivo de consulta</h3>
                <p>{patient.reason_for_consult}</p>
              </div>
            )}
            {patient.relevant_history && (
              <div className="prose-block">
                <h3>Antecedentes</h3>
                <p>{patient.relevant_history}</p>
              </div>
            )}
            <Facts
              items={[
                ['Edad', age !== null ? `${age} años` : null],
                ['Nacimiento', formatDate(patient.birth_date)],
                ['Género', labelOf(meta?.genders, patient.gender)],
                ['Documento', patient.document_id ? `${patient.document_type ?? ''} ${patient.document_id}` : null],
                ['Correo', patient.email],
                ['Teléfono', patient.phone],
                ['Ciudad', patient.city],
                ['Ocupación', patient.occupation],
                ['Medicación', patient.current_medication],
                ['Remitido por', patient.referred_by],
                ['Contacto de emergencia', patient.emergency_contact_name ? `${patient.emergency_contact_name} · ${patient.emergency_contact_phone ?? ''}` : null],
                ['En la clínica desde', formatDate(patient.created_at)],
              ]}
            />
          </div>
        </Panel>

        <Panel title="Evolución en instrumentos" titleId="evolucion">
          {bundle.series.length === 0 ? (
            <EmptyState icon="chart" title="Sin evaluaciones completadas" text="Cuando el paciente responda un cuestionario verás aquí su curva." />
          ) : (
            <div className="section-gap">
              {bundle.series.map((serie) => (
                <div key={serie.code}>
                  <p className="small">
                    <strong>{serie.code}</strong> <span className="muted">· máximo {serie.maxScore}</span>
                  </p>
                  <LineChart
                    label={`Evolución del ${serie.code}`}
                    max={serie.maxScore}
                    height={150}
                    points={serie.points.map((point) => ({
                      label: formatDate(point.administered_at, { day: 'numeric', month: 'short' }),
                      value: Number(point.total_score),
                      title: `${formatDate(point.administered_at)}: ${point.total_score} (${point.severity})`,
                    }))}
                  />
                </div>
              ))}
            </div>
          )}
        </Panel>

        {bundle.moodSeries.length > 0 && (
          <Panel title="Ánimo reportado en sesión" subtitle="Escala de 0 a 10" titleId="animo">
            <LineChart
              label="Estado de ánimo por sesión"
              max={10}
              height={150}
              points={bundle.moodSeries.map((point) => ({
                label: formatDate(point.session_date, { day: 'numeric', month: 'short' }),
                value: Number(point.mood_score),
              }))}
            />
          </Panel>
        )}
      </div>

      <div className="section-gap">
        <Panel title="Línea de tiempo" titleId="linea">
          {bundle.timeline.length === 0 ? (
            <EmptyState icon="clock" title="Todavía no hay actividad" />
          ) : (
            <ol className="timeline">
              {bundle.timeline.map((event, index) => (
                <li key={`${event.type}-${index}`} className="timeline__item">
                  <span className={`timeline__dot timeline__dot--${event.type}`} aria-hidden="true" />
                  <span>
                    <Link className="timeline__title" to={event.link}>
                      {event.title}
                    </Link>
                    <span className="timeline__meta"> · {event.meta}</span>
                  </span>
                  <span className="timeline__date">{formatDate(event.at)}</span>
                </li>
              ))}
            </ol>
          )}
        </Panel>
        <PortalAccess bundle={bundle} />
      </div>
    </div>
  );
}
