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

/** Etiquetas de cada campo segun el formato elegido. */
const SECTIONS: Record<string, { key: keyof Values; label: string; hint: string }[]> = {
  soap: [
    { key: 'subjective', label: 'Subjetivo', hint: 'Lo que la persona relata: motivo, vivencias, cambios desde la última sesión.' },
    { key: 'objective', label: 'Objetivo', hint: 'Lo observado: presentación, afecto, conducta, resultados de instrumentos.' },
    { key: 'assessment', label: 'Análisis', hint: 'Tu lectura clínica de la sesión.' },
    { key: 'plan', label: 'Plan', hint: 'Próximos pasos y objetivos para la siguiente sesión.' },
  ],
  dap: [
    { key: 'subjective', label: 'Datos', hint: 'Lo relatado y lo observado durante la sesión.' },
    { key: 'assessment', label: 'Análisis', hint: 'Interpretación clínica.' },
    { key: 'plan', label: 'Plan', hint: 'Próximos pasos.' },
  ],
  free: [{ key: 'subjective', label: 'Nota de sesión', hint: 'Registro libre de la sesión.' }],
};

function NoteForm({ note }: { note?: Note }) {
  const { data: meta } = useMeta();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const invalidate = useInvalidate();

  const form = useForm<Values>({
    initial: {
      patient_id: note ? String(note.patient_id) : params.get('paciente') ?? '',
      appointment_id: note?.appointment_id ? String(note.appointment_id) : params.get('cita') ?? '',
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
    validate: (values): Record<string, string> => (values.patient_id ? {} : { patient_id: 'Elige el paciente de la sesión.' }),
    onSubmit: async (values) => {
      if (note) {
        const result = await put(`/api/notes/${note.id}`, values);
        toast.success(result.message ?? 'Nota actualizada.');
        await invalidate(['note', String(note.id)], ['notes'], ['patient', values.patient_id]);
        navigate(`/app/notas/${note.id}`);
      } else {
        const result = await post<{ id: number }>('/api/notes', values);
        toast.success(result.message ?? 'Nota guardada.');
        await invalidate(['notes'], ['dashboard'], ['patient', values.patient_id]);
        navigate(`/app/notas/${result.data.id}`);
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
              label="Paciente"
              placeholder="Elige un paciente"
              wrapperClassName="span-full"
              disabled={Boolean(note)}
              options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              {...form.bind('patient_id')}
            />
            <TextField label="Fecha de la sesión" type="date" {...form.bind('session_date')} />
            <TextField label="Número de sesión" type="number" min={1} {...form.bind('session_number')} />
            <SelectField label="Formato" options={meta?.noteFormats ?? []} {...form.bind('format')} />
            <SelectField
              label="Cita asociada"
              optional
              placeholder="Ninguna"
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
              <legend className="field__label">Intervenciones de la sesión</legend>
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
            <TextAreaField label="Tareas acordadas" optional rows={3} {...form.bind('homework')} />
          </div>
        </Panel>

        <div className="section-gap">
          <Panel title="Valoración" titleId="valoracion">
            <div className="inline-form">
              <TextField
                label="Ánimo percibido"
                type="number"
                min={0}
                max={10}
                optional
                hint="De 0 (muy bajo) a 10 (muy bueno)."
                {...form.bind('mood_score')}
              />
              <SelectField
                label="Nivel de riesgo"
                hint="Actualiza también el riesgo en la ficha."
                options={meta?.riskLevels ?? []}
                {...form.bind('risk_level')}
              />
              {(form.values.risk_level === 'moderate' || form.values.risk_level === 'high') && (
                <Alert tone="warning">El paciente aparecerá en “Requieren atención” en el inicio.</Alert>
              )}
            </div>
          </Panel>
          <Panel>
            <div className="stack">
              <Button type="submit" variant="primary" block loading={form.submitting} loadingLabel="Guardando">
                {note ? 'Guardar cambios' : 'Guardar nota'}
              </Button>
              <ButtonLink to={note ? `/app/notas/${note.id}` : '/app/notas'} variant="quiet" block>
                Cancelar
              </ButtonLink>
              <p className="xsmall muted">La nota se guarda como borrador. Podrás revisarla y firmarla después.</p>
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
  useDocumentTitle(id ? 'Editar nota' : 'Nueva nota de sesión');

  const query = useQuery({
    queryKey: ['note', id],
    queryFn: () => get<{ note: Note }>(`/api/notes/${id}`),
    enabled: Boolean(id),
  });

  return (
    <>
      <PageHeader
        back={id ? { to: `/app/notas/${id}`, label: 'Volver a la nota' } : { to: '/app/notas', label: 'Notas' }}
        title={id ? 'Editar nota de sesión' : 'Nueva nota de sesión'}
      />
      <QueryState isPending={metaPending || (Boolean(id) && query.isPending)} error={query.error} onRetry={query.refetch}>
        {query.data?.note.is_locked ? (
          <Alert tone="info" title="Nota firmada">
            Esta nota ya fue firmada y no admite cambios.
          </Alert>
        ) : (
          (!id || query.data) && <NoteForm note={query.data?.note} />
        )}
      </QueryState>
    </>
  );
}
