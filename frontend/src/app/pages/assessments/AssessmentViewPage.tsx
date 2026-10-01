import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router';
import { LineChart } from '@/components/charts/Charts';
import { Button } from '@/components/ui/Button';
import { Alert, Badge, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState, useConfirm } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { del, get } from '@/lib/api';
import { documentText } from '@/lib/documentText';
import { formatDate, formatDateTime, fullName } from '@/lib/format';
import { severityTone } from '@/lib/meta';
import type { Assessment, Instrument, SeriesPoint } from '@/lib/types';
import { DocumentLanguageSelect, useDocumentLanguage } from '../../components/DocumentLanguageSelect';
import { useAction } from '../../useAction';

/** Tone for each band based on its label: some scales score in reverse. */
function bandTone(label: string): 'success' | 'info' | 'warning' | 'danger' {
  const tone = severityTone(label);
  return tone === 'success' || tone === 'warning' || tone === 'danger' ? tone : 'info';
}

interface AssessmentDetail {
  assessment: Assessment;
  instrument: Instrument;
  answers: number[];
  subscales: Record<string, { score: number; severity: string }>;
  alerts: string[];
  series: SeriesPoint[];
}


export default function AssessmentViewPage() {
  const { id = '' } = useParams();
  const confirm = useConfirm();
  const navigate = useNavigate();
  const [language, setLanguage] = useDocumentLanguage();
  const t = documentText(language);
  const query = useQuery({
    queryKey: ['assessment', id, language],
    queryFn: () => get<AssessmentDetail>(`/api/assessments/${id}`, { language }),
    placeholderData: keepPreviousData,
  });
  const detail = query.data;
  useDocumentTitle(detail ? `${detail.instrument.code} · ${fullName(detail.assessment)}` : 'Assessment');

  const remove = useAction(() => del(`/api/assessments/${id}`), {
    invalidate: [['assessments'], ['patient']],
    onSuccess: () => navigate('/app/assessments'),
  });

  const onDelete = async () => {
    const ok = await confirm({ title: 'Delete assessment', text: "The score and answers will be deleted. This can't be undone.", confirmLabel: 'Delete', danger: true });
    if (ok) await remove.run(undefined).catch(() => undefined);
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && (
        <>
          <PageHeader
            back={{ to: `/app/patients/${detail.assessment.patient_id}?tab=assessments`, label: fullName(detail.assessment) }}
            title={`${detail.instrument.code} · ${detail.instrument.domain}`}
            subtitle={`${detail.instrument.name} · ${t.assessment.administeredOn(formatDateTime(detail.assessment.administered_at, t.locale))}`}
            actions={
              <>
                <DocumentLanguageSelect value={language} onChange={setLanguage} />
                <Button icon="print" onClick={() => window.print()}>
                  Print
                </Button>
                <Button variant="quiet" iconOnly icon="trash" aria-label="Delete assessment" onClick={onDelete} />
              </>
            }
          />

          {detail.alerts.length > 0 && (
            <div className="risk-banner">
              <Alert tone="error" title={t.assessment.criticalTitle}>
                <ul>
                  {detail.alerts.map((alert) => (
                    <li key={alert}>{alert}</li>
                  ))}
                </ul>
              </Alert>
            </div>
          )}

          <div className="layout-2" lang={language}>
            <div className="section-gap">
              <Panel title={t.assessment.result} titleId="result">
                <div className="stack">
                  <div className="score">
                    <span className="score__value">{detail.assessment.total_score}</span>
                    <span className="score__max">/ {detail.instrument.maxScore}</span>
                    <Badge tone={severityTone(detail.assessment.severity)}>{detail.assessment.severity}</Badge>
                  </div>
                  {detail.assessment.interpretation && <p className="soft">{detail.assessment.interpretation}</p>}
                  {Object.keys(detail.subscales).length > 0 && (
                    <dl className="facts">
                      {Object.entries(detail.subscales).map(([name, value]) => (
                        <div key={name}>
                          <dt>{name}</dt>
                          <dd>
                            {value.score} · <Badge tone={severityTone(value.severity)}>{value.severity}</Badge>
                          </dd>
                        </div>
                      ))}
                    </dl>
                  )}
                  {detail.assessment.clinician_notes && (
                    <div className="prose-block">
                      <h3>{t.assessment.clinicianNotes}</h3>
                      <p>{detail.assessment.clinician_notes}</p>
                    </div>
                  )}
                  <p className="xsmall muted">{t.assessment.disclaimer}</p>
                </div>
              </Panel>

              {detail.series.length > 1 && (
                <Panel title={t.assessment.progress} subtitle={t.assessment.administrations(detail.series.length)} titleId="progress">
                  <LineChart
                    label={t.assessment.overTime(detail.instrument.code)}
                    max={detail.instrument.maxScore}
                    bands={detail.instrument.bands.map((band) => ({
                      from: band.min,
                      to: band.max,
                      tone: bandTone(band.label),
                    }))}
                    points={detail.series.map((point) => ({
                      label: formatDate(point.administered_at, { day: 'numeric', month: 'short' }, t.locale),
                      value: Number(point.total_score),
                    }))}
                  />
                </Panel>
              )}

              <Panel title={t.assessment.answers} titleId="answers" flush>
                <div className="table-wrap">
                  <table className="table">
                    <tbody>
                      {detail.instrument.items.map((item, index) => {
                        const value = detail.answers[index];
                        const label = detail.instrument.scale.find((option) => option.value === value)?.label ?? '—';
                        const critical = detail.instrument.criticalItems.includes(index);
                        return (
                          <tr key={index}>
                            <td className="muted tabular">{index + 1}</td>
                            <td>
                              {item}
                              {critical && <span className="likert__critical"> · {t.assessment.critical}</span>}
                            </td>
                            <td>{label}</td>
                            <td className="num">{value ?? '—'}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </Panel>
            </div>

            <Panel title={t.assessment.bands} titleId="bands" flush>
              <ul className="list">
                {detail.instrument.bands.map((band) => (
                  <li key={band.label} className="list__item">
                    <span className="list__main">
                      <span className="list__title">{band.label}</span>
                      {band.interpretation && <span className="list__meta">{band.interpretation}</span>}
                    </span>
                    <span className="tabular small">
                      {band.min}–{band.max}
                    </span>
                  </li>
                ))}
              </ul>
            </Panel>
          </div>
        </>
      )}
    </QueryState>
  );
}
