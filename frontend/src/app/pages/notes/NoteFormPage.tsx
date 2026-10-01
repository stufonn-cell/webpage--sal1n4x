import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, post, put } from '@/lib/api';
import { formatDateTime, toISODate } from '@/lib/format';
import { useMeta } from '@/lib/meta';
import type { Note } from '@/lib/types';
import { useInvalidate } from '../../useAction';

type Values = {
  patient_id: string;
  appointment_id: string;
  session_number: string;
  session_date: string;
  format: string;
  subjective: string;
  objective: string;
  assessment: string;
  plan: string;
  interventions: string[];
  homework: string;
  mood_score: string;
  risk_level: string;
};

/** Field labels for each note format. */
const SECTIONS: Record<string, { key: keyof Values; label: string; hint: string }[]> = {
  soap: [
    { key: 'subjective', label: 'Subjective', hint: 'What the person shares: reason, experiences, changes since the last session.' },
    { key: 'objective', label: 'Objective', hint: 'What you observe: presentation, affect, behavior, instrument results.' },
    { key: 'assessment', label: 'Assessment', hint: 'Your clinical reading of the session.' },
    { key: 'plan', label: 'Plan', hint: 'Next steps and goals for the next session.' },
  ],
  dap: [
    { key: 'subjective', label: 'Data', hint: 'What was shared and observed during the session.' },
    { key: 'assessment', label: 'Assessment', hint: 'Clinical interpretation.' },
    { key: 'plan', label: 'Plan', hint: 'Next steps.' },
  ],
  free: [{ key: 'subjective', label: 'Session note', hint: 'A free-form record of the session.' }],
};

function NoteForm({ note }: { note?: Note }) {
  const { data: meta } = useMeta();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const invalidate = useInvalidate();

  const form = useForm<Values>({
    initial: {
      patient_id: note ? String(note.patient_id) : params.get('patient') ?? '',
      appointment_id: note?.appointment_id ? String(note.appointment_id) : params.get('appointment') ?? '',
      session_number: note ? String(note.session_number) : '',
      session_date: note?.session_date ?? toISODate(new Date()),
      format: note?.format ?? 'soap',
      subjective: note?.subjective ?? '',
      objective: note?.objective ?? '',
      assessment: note?.assessment ?? '',
      plan: note?.plan ?? '',
      interventions: note?.interventions ? note.interventions.split(', ').filter(Boolean) : [],
      homework: note?.homework ?? '',
      mood_score: note?.mood_score !== null && note?.mood_score !== undefined ? String(note.mood_score) : '',
      risk_level: note?.risk_level ?? 'none',
    },
    validate: (values): Record<string, string> => (values.patient_id ? {} : { patient_id: 'Choose the patient for this session.' }),
    onSubmit: async (values) => {
      if (note) {
        const result = await put(`/api/notes/${note.id}`, values);
        toast.success(result.message ?? 'Note updated.');
        await invalidate(['note', String(note.id)], ['notes'], ['patient', values.patient_id]);
        navigate(`/app/notes/${note.id}`);
      } else {
        const result = await post<{ id: number }>('/api/notes', values);
        toast.success(result.message ?? 'Note saved.');
        await invalidate(['notes'], ['dashboard'], ['patient', values.patient_id]);
        navigate(`/app/notes/${result.data.id}`);
      }
    },
  });

  const patientId = form.values.patient_id;
  const context = useQuery({
    queryKey: ['note-context', patientId],
    queryFn: () =>
      get<{ nextSessionNumber: number; appointments: { id: number; starts_at: string; status: string }[] }>(
        `/api/patients/${patientId}/note-context`,
      ),
    enabled: Boolean(patientId),
  });

  const { set } = form;
  useEffect(() => {
    if (!note && context.data) set('session_number', String(context.data.nextSessionNumber));
  }, [context.data, note, set]);

  const toggleIntervention = (item: string) => {
    const current = form.values.interventions;
    form.set('interventions', current.includes(item) ? current.filter((value) => value !== item) : [...current, item]);
  };

  const sections = SECTIONS[form.values.format] ?? SECTIONS.soap;

  return (
    <form onSubmit={form.handleSubmit} noValidate>
      <div className="layout-aside">
        <Panel>
          <FormAlert message={form.formError} />
          <div className="form-grid">
            <SelectField
              label="Patient"
              placeholder="Choose a patient"
              wrapperClassName="span-full"
              disabled={Boolean(note)}
              options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              {...form.bind('patient_id')}
            />
            <TextField label="Session date" type="date" {...form.bind('session_date')} />
            <TextField label="Session number" type="number" min={1} {...form.bind('session_number')} />
            <SelectField label="Format" options={meta?.noteFormats ?? []} {...form.bind('format')} />
            <SelectField
              label="Linked appointment"
              optional
              placeholder="None"
              options={(context.data?.appointments ?? []).map((item) => ({ value: String(item.id), label: formatDateTime(item.starts_at) }))}
              {...form.bind('appointment_id')}
            />
          </div>

          <div className="form-section">
            <div className="section-gap">
              {sections.map((section) => (
                <TextAreaField key={section.key} label={section.label} hint={section.hint} rows={5} {...form.bind(section.key as 'subjective')} />
              ))}
            </div>
          </div>

          <div className="form-section section-gap">
            <fieldset className="request__group">
              <legend className="field__label">Session interventions</legend>
              <div className="chip-set">
                {(meta?.interventions ?? []).map((item) => (
                  <label key={item} className="chip">
                    <input type="checkbox" checked={form.values.interventions.includes(item)} onChange={() => toggleIntervention(item)} />
                    {form.values.interventions.includes(item) && <Icon name="check" size={14} />}
                    {item}
                  </label>
                ))}
              </div>
            </fieldset>
            <TextAreaField label="Agreed homework" optional rows={3} {...form.bind('homework')} />
          </div>
        </Panel>

        <div className="section-gap">
          <Panel title="Clinical rating" titleId="rating">
            <div className="inline-form">
              <TextField
                label="Perceived mood"
                type="number"
                min={0}
                max={10}
                optional
                hint="From 0 (very low) to 10 (very good)."
                {...form.bind('mood_score')}
              />
              <SelectField
                label="Risk level"
                hint="This also updates the risk level on the patient file."
                options={meta?.riskLevels ?? []}
                {...form.bind('risk_level')}
              />
              {(form.values.risk_level === 'moderate' || form.values.risk_level === 'high') && (
                <Alert tone="warning">The patient will appear under “Needs attention” on the home page.</Alert>
              )}
            </div>
          </Panel>
          <Panel>
            <div className="stack">
              <Button type="submit" variant="primary" block loading={form.submitting} loadingLabel="Saving">
                {note ? 'Save changes' : 'Save note'}
              </Button>
              <ButtonLink to={note ? `/app/notes/${note.id}` : '/app/notes'} variant="quiet" block>
                Cancel
              </ButtonLink>
              <p className="xsmall muted">The note is saved as a draft. You can review and sign it later.</p>
            </div>
          </Panel>
        </div>
      </div>
    </form>
  );
}

export default function NoteFormPage() {
  const { id } = useParams();
  const { isPending: metaPending } = useMeta();
  useDocumentTitle(id ? 'Edit note' : 'New session note');

  const query = useQuery({
    queryKey: ['note', id],
    queryFn: () => get<{ note: Note }>(`/api/notes/${id}`),
    enabled: Boolean(id),
  });

  return (
    <>
      <PageHeader
        back={id ? { to: `/app/notes/${id}`, label: 'Back to the note' } : { to: '/app/notes', label: 'Notes' }}
        title={id ? 'Edit session note' : 'New session note'}
      />
      <QueryState isPending={metaPending || (Boolean(id) && query.isPending)} error={query.error} onRetry={query.refetch}>
        {query.data?.note.is_locked ? (
          <Alert tone="info" title="Signed note">
            This note has already been signed and can't be changed.
          </Alert>
        ) : (
          (!id || query.data) && <NoteForm note={query.data?.note} />
        )}
      </QueryState>
    </>
  );
}
