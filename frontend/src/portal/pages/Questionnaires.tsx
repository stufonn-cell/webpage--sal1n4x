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
  useDocumentTitle('Questionnaires');
  const query = useQuery({ queryKey: ['portal', 'questionnaires'], queryFn: () => get<PortalAssessment[]>('/api/portal/questionnaires') });
  const pending = query.data?.filter((item) => item.status === 'pending') ?? [];
  const completed = query.data?.filter((item) => item.status === 'completed') ?? [];

  // One curve per instrument with at least two administrations.
  const byCode = completed.reduce<Record<string, PortalAssessment[]>>((groups, item) => {
    (groups[item.instrument_code] ??= []).push(item);
    return groups;
  }, {});

  return (
    <>
      <PageHeader title="Questionnaires" subtitle="They help you and your professional see how you are doing. There are no right or wrong answers." />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        <div className="section-gap">
          <Panel title="To answer" titleId="to-answer" flush>
            {pending.length === 0 ? (
              <EmptyState icon="checkCircle" title="You are all caught up" text="When your professional assigns you a questionnaire, it will show up here." />
            ) : (
              <ul className="list">
                {pending.map((item) => (
                  <li key={item.id}>
                    <Link className="list__item" to={`/portal/questionnaires/${item.id}`}>
                      <span className="list__main">
                        <span className="list__title">{item.instrument_name}</span>
                        <span className="list__meta">
                          {item.items_count} questions · assigned on {formatDate(item.created_at)}
                        </span>
                      </span>
                      <span className="btn btn--sm btn--primary">Answer</span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          {Object.entries(byCode)
            .filter(([, items]) => items.length > 1)
            .map(([code, items]) => (
              <Panel key={code} title={`Your progress · ${items[0].instrument_domain}`} subtitle={items[0].instrument_name} titleId={`progress-${code}`}>
                <LineChart
                  label={`Your progress on ${items[0].instrument_name}`}
                  max={items[0].max_score}
                  height={150}
                  points={[...items].reverse().map((item) => ({
                    label: formatDate(item.administered_at, { day: 'numeric', month: 'short' }),
                    value: Number(item.total_score),
                  }))}
                />
                <p className="xsmall muted">Your professional will help you understand these results in session.</p>
              </Panel>
            ))}

          {completed.length > 0 && (
            <Panel title="Answered" titleId="answered" flush>
              <ul className="list">
                {completed.map((item) => (
                  <li key={item.id} className="list__item">
                    <span className="list__main">
                      <span className="list__title">{item.instrument_name}</span>
                      <span className="list__meta">{formatDate(item.administered_at)}</span>
                    </span>
                    <Badge tone="success">Submitted</Badge>
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
  useDocumentTitle(instrument?.name ?? 'Questionnaire');

  const submit = async () => {
    if (!instrument) return;
    const missing = instrument.items.findIndex((_, index) => answers[index] === undefined);
    if (missing >= 0) {
      setShowMissing(true);
      setError('Some questions still need an answer. The missing ones are marked so you can find them easily.');
      document.getElementById(`item-${missing}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    setSending(true);
    setError('');
    try {
      const result = await post(`/api/portal/questionnaires/${id}`, { answers: instrument.items.map((_, index) => answers[index]) });
      toast.success(result.message ?? 'Thank you for your answers.');
      await queryClient.invalidateQueries({ queryKey: ['portal'] });
      navigate('/portal/questionnaires');
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'We could not send your answers. Please try again.');
    } finally {
      setSending(false);
    }
  };

  const conflict = query.error instanceof ApiError && query.error.status === 409;

  return (
    <div className="container--narrow">
      <PageHeader back={{ to: '/portal/questionnaires', label: 'Questionnaires' }} title={instrument?.name ?? 'Questionnaire'} />
      {conflict ? (
        <EmptyState
          icon="checkCircle"
          title="You already answered this questionnaire"
          text="Thank you. Your professional will go over it with you."
          action={<ButtonLink to="/portal" size="sm">Back to home</ButtonLink>}
        />
      ) : (
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {instrument && (
            <div className="section-gap">
              <Alert tone="info" icon="heart">
                Answer thinking about <strong>{instrument.window.toLowerCase()}</strong>. Choose the option that best describes how you
                have been feeling; there are no good or bad answers. Take all the time you need.
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
                  <Icon name="lock" size={13} className="inline-icon" /> Only your professional will see your answers.
                </p>
                <Button variant="primary" size="lg" onClick={submit} loading={sending} loadingLabel="Sending">
                  Send answers
                </Button>
              </div>
            </div>
          )}
        </QueryState>
      )}
    </div>
  );
}
