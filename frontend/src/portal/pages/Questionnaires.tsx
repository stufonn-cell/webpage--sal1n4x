import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { LineChart } from '@/components/charts/Charts';
import { Questionnaire } from '@/components/forms/Questionnaire';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ApiError, get, post } from '@/lib/api';
import { formatDate } from '@/lib/format';
import type { Instrument } from '@/lib/types';
import type { PortalAssessment } from '../types';

export function QuestionnairesPage() {
  useDocumentTitle('Cuestionarios');
  const query = useQuery({ queryKey: ['portal', 'questionnaires'], queryFn: () => get<PortalAssessment[]>('/api/portal/questionnaires') });
  const pending = query.data?.filter((item) => item.status === 'pending') ?? [];
  const completed = query.data?.filter((item) => item.status === 'completed') ?? [];

  // Una curva por instrumento con al menos dos aplicaciones.
  const byCode = completed.reduce<Record<string, PortalAssessment[]>>((groups, item) => {
    (groups[item.instrument_code] ??= []).push(item);
    return groups;
  }, {});

  return (
    <>
      <PageHeader title="Cuestionarios" subtitle="Te ayudan a ti y a tu profesional a ver cómo vas. No hay respuestas correctas ni incorrectas." />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        <div className="section-gap">
          <Panel title="Por responder" titleId="por-responder" flush>
            {pending.length === 0 ? (
              <EmptyState icon="checkCircle" title="Estás al día" text="Cuando tu profesional te asigne un cuestionario aparecerá aquí." />
            ) : (
              <ul className="list">
                {pending.map((item) => (
                  <li key={item.id}>
                    <Link className="list__item" to={`/portal/cuestionarios/${item.id}`}>
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">
                          {item.items_count} preguntas · asignado el {formatDate(item.created_at)}
                        </span>
                      </span>
                      <span className="btn btn--sm btn--primary">Responder</span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          {Object.entries(byCode)
            .filter(([, items]) => items.length > 1)
            .map(([code, items]) => (
              <Panel key={code} title={`Tu evolución · ${items[0].instrument_domain}`} subtitle={items[0].instrument_name} titleId={`evolucion-${code}`}>
                <LineChart
                  label={`Tu evolución en ${items[0].instrument_name}`}
                  max={items[0].max_score}
                  height={150}
                  points={[...items].reverse().map((item) => ({
                    label: formatDate(item.administered_at, { day: 'numeric', month: 'short' }),
                    value: Number(item.total_score),
                  }))}
                />
                <p className="xsmall muted">Tu profesional te ayudará a entender estos resultados en sesión.</p>
              </Panel>
            ))}

          {completed.length > 0 && (
            <Panel title="Respondidos" titleId="respondidos" flush>
              <ul className="list">
                {completed.map((item) => (
                  <li key={item.id} className="list__item">
                    <span className="list__main">
                      <span className="list__title">{item.instrument_name}</span>
                      <span className="list__meta">{formatDate(item.administered_at)}</span>
                    </span>
                    <Badge tone="success">Enviado</Badge>
                  </li>
                ))}
              </ul>
            </Panel>
          )}
        </div>
      </QueryState>
    </>
  );
}

export function QuestionnairePage() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [showMissing, setShowMissing] = useState(false);
  const [error, setError] = useState('');
  const [sending, setSending] = useState(false);

  const query = useQuery({
    queryKey: ['portal', 'questionnaire', id],
    queryFn: () => get<{ id: number; instrument: Instrument }>(`/api/portal/questionnaires/${id}`),
  });
  const instrument = query.data?.instrument;
  useDocumentTitle(instrument?.name ?? 'Cuestionario');

  const submit = async () => {
    if (!instrument) return;
    const missing = instrument.items.findIndex((_, index) => answers[index] === undefined);
    if (missing >= 0) {
      setShowMissing(true);
      setError('Te falta responder alguna pregunta. Las que faltan están marcadas para que las encuentres fácil.');
      document.getElementById(`item-${missing}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    setSending(true);
    setError('');
    try {
      const result = await post(`/api/portal/questionnaires/${id}`, { answers: instrument.items.map((_, index) => answers[index]) });
      toast.success(result.message ?? 'Gracias por responder.');
      await queryClient.invalidateQueries({ queryKey: ['portal'] });
      navigate('/portal/cuestionarios');
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No pudimos enviar tus respuestas. Inténtalo de nuevo.');
    } finally {
      setSending(false);
    }
  };

  const conflict = query.error instanceof ApiError && query.error.status === 409;

  return (
    <div className="container--narrow">
      <PageHeader back={{ to: '/portal/cuestionarios', label: 'Cuestionarios' }} title={instrument?.name ?? 'Cuestionario'} />
      {conflict ? (
        <EmptyState
          icon="checkCircle"
          title="Ya respondiste este cuestionario"
          text="Gracias. Tu profesional lo revisará contigo."
          action={<ButtonLink to="/portal" size="sm">Volver al inicio</ButtonLink>}
        />
      ) : (
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {instrument && (
            <div className="section-gap">
              <Alert tone="info" icon="heart">
                Responde pensando en <strong>{instrument.window.toLowerCase()}</strong>. Elige la opción que mejor describa cómo te has
                sentido; no hay respuestas buenas ni malas. Puedes tomarte el tiempo que necesites.
              </Alert>
              <Questionnaire
                instrument={instrument}
                answers={answers}
                audience="patient"
                highlightMissing={showMissing}
                onAnswer={(index, value) => setAnswers((current) => ({ ...current, [index]: value }))}
              />
              <FormAlert message={error} />
              <div className="split">
                <p className="xsmall muted">
                  <Icon name="lock" size={13} className="inline-icon" /> Solo tu profesional verá tus respuestas.
                </p>
                <Button variant="primary" size="lg" onClick={submit} loading={sending} loadingLabel="Enviando">
                  Enviar respuestas
                </Button>
              </div>
            </div>
          )}
        </QueryState>
      )}
    </div>
  );
}
