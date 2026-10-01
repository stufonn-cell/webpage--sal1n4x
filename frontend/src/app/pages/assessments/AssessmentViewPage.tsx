import { useQuery } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router';
import { LineChart } from '@/components/charts/Charts';
import { Button } from '@/components/ui/Button';
import { Alert, Badge, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState, useConfirm } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { del, get } from '@/lib/api';
import { formatDate, formatDateTime, fullName, pretty } from '@/lib/format';
import { severityTone } from '@/lib/meta';
import type { Assessment, Instrument, SeriesPoint } from '@/lib/types';
import { useAction } from '../../useAction';

/** Tono de cada banda segun su etiqueta: algunas escalas puntuan al reves. */
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
  const query = useQuery({ queryKey: ['assessment', id], queryFn: () => get<AssessmentDetail>(`/api/assessments/${id}`) });
  const detail = query.data;
  useDocumentTitle(detail ? `${detail.instrument.code} · ${fullName(detail.assessment)}` : 'Evaluación');

  const remove = useAction(() => del(`/api/assessments/${id}`), {
    invalidate: [['assessments'], ['patient']],
    onSuccess: () => navigate('/app/evaluaciones'),
  });

  const onDelete = async () => {
    const ok = await confirm({ title: 'Eliminar evaluación', text: 'Se eliminarán el puntaje y las respuestas. No se puede deshacer.', confirmLabel: 'Eliminar', danger: true });
    if (ok) await remove.run(undefined).catch(() => undefined);
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && (
        <>
          <PageHeader
            back={{ to: `/app/pacientes/${detail.assessment.patient_id}?tab=evaluaciones`, label: fullName(detail.assessment) }}
            title={`${detail.instrument.code} · ${detail.instrument.domain}`}
            subtitle={`${detail.instrument.name} · aplicado el ${formatDateTime(detail.assessment.administered_at)}`}
            actions={
              <>
                <Button icon="print" onClick={() => window.print()}>
                  Imprimir
                </Button>
                <Button variant="quiet" iconOnly icon="trash" aria-label="Eliminar evaluación" onClick={onDelete} />
              </>
            }
          />

          {detail.alerts.length > 0 && (
            <div className="risk-banner">
              <Alert tone="error" title="Ítems críticos con respuesta positiva">
                <ul>
                  {detail.alerts.map((alert) => (
                    <li key={alert}>{alert}</li>
                  ))}
                </ul>
              </Alert>
            </div>
          )}

          <div className="layout-2">
            <div className="section-gap">
              <Panel title="Resultado" titleId="resultado">
                <div className="stack">
                  <div className="score">
                    <span className="score__value">{detail.assessment.total_score}</span>
                    <span className="score__max">/ {detail.instrument.maxScore}</span>
                    <Badge tone={severityTone(detail.assessment.severity)}>{pretty(detail.assessment.severity)}</Badge>
                  </div>
                  {detail.assessment.interpretation && <p className="soft">{pretty(detail.assessment.interpretation)}</p>}
                  {Object.keys(detail.subscales).length > 0 && (
                    <dl className="facts">
                      {Object.entries(detail.subscales).map(([name, value]) => (
                        <div key={name}>
                          <dt>{pretty(name)}</dt>
                          <dd>
                            {value.score} · <Badge tone={severityTone(value.severity)}>{pretty(value.severity)}</Badge>
                          </dd>
                        </div>
                      ))}
                    </dl>
                  )}
                  {detail.assessment.clinician_notes && (
                    <div className="prose-block">
                      <h3>Observaciones</h3>
                      <p>{detail.assessment.clinician_notes}</p>
                    </div>
                  )}
                  <p className="xsmall muted">Resultado orientativo. No constituye un diagnóstico ni sustituye la valoración clínica.</p>
                </div>
              </Panel>

              {detail.series.length > 1 && (
                <Panel title="Evolución" subtitle={`${detail.series.length} aplicaciones`} titleId="evolucion">
                  <LineChart
                    label={`Evolución del ${detail.instrument.code}`}
                    max={detail.instrument.maxScore}
                    bands={detail.instrument.bands.map((band) => ({
                      from: band.min,
                      to: band.max,
                      tone: bandTone(band.label),
                    }))}
                    points={detail.series.map((point) => ({
                      label: formatDate(point.administered_at, { day: 'numeric', month: 'short' }),
                      value: Number(point.total_score),
                    }))}
                  />
                </Panel>
              )}

              <Panel title="Respuestas ítem por ítem" titleId="respuestas" flush>
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
                              {critical && <span className="likert__critical"> · crítico</span>}
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

            <Panel title="Bandas de referencia" titleId="bandas" flush>
              <ul className="list">
                {detail.instrument.bands.map((band) => (
                  <li key={band.label} className="list__item">
                    <span className="list__main">
                      <span className="list__title">{pretty(band.label)}</span>
                      {band.interpretation && <span className="list__meta">{pretty(band.interpretation)}</span>}
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
