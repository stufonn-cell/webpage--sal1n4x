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
  const code = params.get('instrumento') ?? 'PHQ-9';
  const [patientId, setPatientId] = useState(params.get('paciente') ?? '');
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');
  const [patientError, setPatientError] = useState('');
  const [showMissing, setShowMissing] = useState(false);
  const [saving, setSaving] = useState(false);
  useDocumentTitle('Aplicar instrumento');

  const query = useQuery({
    queryKey: ['instrument', code],
    queryFn: () => get<Instrument>(`/api/instruments/${code}`),
    staleTime: Infinity,
  });
  const instrument = query.data;

  const changeInstrument = (next: string) => {
    const nextParams = new URLSearchParams(params);
    nextParams.set('instrumento', next);
    setParams(nextParams, { replace: true });
    setAnswers({});
    setShowMissing(false);
  };

  const submit = async () => {
    if (!instrument) return;
    setError('');
    if (!patientId) {
      setPatientError('Elige a quién se le aplica.');
      document.getElementById('assessment-patient')?.focus();
      return;
    }
    const missing = instrument.items.findIndex((_, index) => answers[index] === undefined);
    if (missing >= 0) {
      setShowMissing(true);
      setError('Faltan ítems por responder. Están marcados en rojo.');
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
      toast.success(result.message ?? 'Evaluación registrada.');
      await invalidate(['assessments'], ['patient', patientId], ['dashboard']);
      navigate(`/app/evaluaciones/${result.data.id}`);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'No pudimos guardar la evaluación.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <PageHeader back={{ to: '/app/evaluaciones', label: 'Evaluaciones' }} title="Aplicar instrumento" subtitle={instrument?.name} />
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
                label="Paciente"
                id="assessment-patient"
                placeholder="Elige un paciente"
                value={patientId}
                error={patientError}
                onChange={(event) => {
                  setPatientId(event.target.value);
                  setPatientError('');
                }}
                options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              />
              <SelectField label="Instrumento" id="assessment-code" value={code} onChange={(event) => changeInstrument(event.target.value)} options={meta?.instruments ?? []} />
              <TextAreaField label="Observaciones" id="assessment-notes" optional rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} />
              <Button variant="primary" block onClick={submit} loading={saving} loadingLabel="Corrigiendo">
                Corregir y guardar
              </Button>
              {instrument && <p className="xsmall muted">{instrument.description}</p>}
            </div>
          </Panel>
        </div>
      </div>
    </>
  );
}
