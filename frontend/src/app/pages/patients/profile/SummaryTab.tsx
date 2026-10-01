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
      title: 'Create portal access',
      text: `We'll create an account for ${patient.first_name} with a temporary password. We'll show it to you only once so you can hand it over in person.`,
      confirmLabel: 'Create access',
    });
    if (ok) await create.run(undefined).catch(() => undefined);
  };

  return (
    <Panel title="Patient portal" titleId="portal">
      {credentials ? (
        <div className="stack">
          <Alert tone="success" title="Access created">
            Hand these details over in person. For security, they won't be shown again.
          </Alert>
          <div className="secret-box">
            <span>Username: {credentials.username}</span>
            <span>Temporary password: {credentials.temporaryPassword}</span>
          </div>
        </div>
      ) : portalAccount ? (
        <Facts
          items={[
            ['Username', portalAccount.username],
            ['Status', portalAccount.is_active ? 'Active' : 'Deactivated'],
            ['Last sign-in', formatDateTime(portalAccount.last_login_at)],
          ]}
        />
      ) : (
        <div className="stack">
          <p className="small soft">
            With the portal, {patient.first_name} can see their appointments, answer questionnaires and sign consents from home.
          </p>
          {!patient.email && <p className="small muted">First, add an email address to their file.</p>}
          <div>
            <Button size="sm" icon="user" onClick={onCreate} disabled={!patient.email} loading={create.pending}>
              Create access
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
        <Panel title="Patient file" titleId="patient-file">
          <div className="section-gap">
            {patient.reason_for_consult && (
              <div className="prose-block">
                <h3>Reason for consultation</h3>
                <p>{patient.reason_for_consult}</p>
              </div>
            )}
            {patient.relevant_history && (
              <div className="prose-block">
                <h3>Relevant history</h3>
                <p>{patient.relevant_history}</p>
              </div>
            )}
            <Facts
              items={[
                ['Age', age !== null ? `${age} years old` : null],
                ['Date of birth', formatDate(patient.birth_date)],
                ['Gender', labelOf(meta?.genders, patient.gender)],
                ['ID document', patient.document_id ? `${patient.document_type ?? ''} ${patient.document_id}` : null],
                ['Sex on ID', patient.biological_sex ? labelOf(meta?.rips.sexes, patient.biological_sex) : null],
                ['Health coverage', labelOf(meta?.rips.userTypes, patient.rips_user_type)],
                [
                  'Residence (DIVIPOLA)',
                  patient.residence_country === '170'
                    ? patient.residence_municipality && `${patient.residence_municipality} · ${labelOf(meta?.rips.zones, patient.residence_zone)}`
                    : `Country ${patient.residence_country}`,
                ],
                ['Email', patient.email],
                ['Phone', patient.phone],
                ['City', patient.city],
                ['Occupation', patient.occupation],
                ['Medication', patient.current_medication],
                ['Referred by', patient.referred_by],
                ['Emergency contact', patient.emergency_contact_name ? `${patient.emergency_contact_name} · ${patient.emergency_contact_phone ?? ''}` : null],
                ['Patient since', formatDate(patient.created_at)],
              ]}
            />
          </div>
        </Panel>

        <Panel title="Scores over time" titleId="progress">
          {bundle.series.length === 0 ? (
            <EmptyState icon="chart" title="No completed assessments" text="Once the patient answers a questionnaire, you'll see their trend here." />
          ) : (
            <div className="section-gap">
              {bundle.series.map((serie) => (
                <div key={serie.code}>
                  <p className="small">
                    <strong>{serie.code}</strong> <span className="muted">· max {serie.maxScore}</span>
                  </p>
                  <LineChart
                    label={`${serie.code} over time`}
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
          <Panel title="Mood reported in session" subtitle="Scale from 0 to 10" titleId="mood">
            <LineChart
              label="Mood by session"
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
        <Panel title="Timeline" titleId="timeline">
          {bundle.timeline.length === 0 ? (
            <EmptyState icon="clock" title="No activity yet" />
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
