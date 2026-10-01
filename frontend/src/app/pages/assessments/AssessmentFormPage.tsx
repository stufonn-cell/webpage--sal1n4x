import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Questionnaire } from '@/components/forms/Questionnaire';
import { Button } from '@/components/ui/Button';
import { PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ApiError, get, post } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import type { Instrument } from '@/lib/types';
import { useInvalidate } from '../../useAction';

export default function AssessmentFormPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const invalidate = useInvalidate();
  const code = params.get('instrument') ?? 'PHQ-9';
  const [patientId, setPatientId] = useState(params.get('patient') ?? '');
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');
  const [patientError, setPatientError] = useState('');
  const [showMissing, setShowMissing] = useState(false);
  const [saving, setSaving] = useState(false);
  useDocumentTitle('Administer instrument');

  const query = useQuery({
    queryKey: ['instrument', code],
    queryFn: () => get<Instrument>(`/api/instruments/${code}`),
    staleTime: Infinity,
  });
  const instrument = query.data;

  const changeInstrument = (next: string) => {
    const nextParams = new URLSearchParams(params);
    nextParams.set('instrument', next);
    setParams(nextParams, { replace: true });
    setAnswers({});
    setShowMissing(false);
  };

  const submit = async () => {
    if (!instrument) return;
    setError('');
    if (!patientId) {
      setPatientError('Choose who is taking it.');
      document.getElementById('assessment-patient')?.focus();
      return;
    }
    const missing = instrument.items.findIndex((_, index) => answers[index] === undefined);
    if (missing >= 0) {
      setShowMissing(true);
      setError('Some items still need an answer. They are marked in red.');
      document.getElementById(`item-${missing}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    setSaving(true);
    try {
      const result = await post<{ id: number }>('/api/assessments', {
        patient_id: Number(patientId),
        instrument_code: instrument.code,
        answers: instrument.items.map((_, index) => answers[index]),
        clinician_notes: notes,
      });
      toast.success(result.message ?? 'Assessment saved.');
      await invalidate(['assessments'], ['patient', patientId], ['dashboard']);
      navigate(`/app/assessments/${result.data.id}`);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : "We couldn't save the assessment.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <PageHeader back={{ to: '/app/assessments', label: 'Assessments' }} title="Administer instrument" subtitle={instrument?.name} />
      <div className="layout-aside">
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {instrument && (
            <Questionnaire
              instrument={instrument}
              answers={answers}
              highlightMissing={showMissing}
              onAnswer={(index, value) => setAnswers((current) => ({ ...current, [index]: value }))}
            />
          )}
        </QueryState>

        <div className="section-gap">
          <Panel>
            <div className="inline-form">
              <FormAlert message={error} />
              <SelectField
                label="Patient"
                id="assessment-patient"
                placeholder="Choose a patient"
                value={patientId}
                error={patientError}
                onChange={(event) => {
                  setPatientId(event.target.value);
                  setPatientError('');
                }}
                options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              />
              <SelectField label="Instrument" id="assessment-code" value={code} onChange={(event) => changeInstrument(event.target.value)} options={meta?.instruments ?? []} />
              <TextAreaField label="Clinician notes" id="assessment-notes" optional rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />
              <Button variant="primary" block onClick={submit} loading={saving} loadingLabel="Scoring">
                Score and save
              </Button>
              {instrument && <p className="xsmall muted">{instrument.description}</p>}
            </div>
          </Panel>
        </div>
      </div>
    </>
  );
}
