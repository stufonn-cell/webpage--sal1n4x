import { Button } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { useConfirm, useToast } from '@/components/ui/Feedback';
import { useForm } from '@/hooks/useForm';
import { del, post } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import { useAction, useInvalidate } from '../../../useAction';
import type { Diagnosis, PatientBundle } from './types';

const SYSTEMS = [
  { value: 'icd10', label: 'CIE-10' },
  { value: 'dsm5', label: 'DSM-5' },
];

const STATUS_TONE: Record<string, 'warning' | 'info' | 'success' | 'neutral'> = {
  active: 'warning',
  remission: 'info',
  resolved: 'success',
  ruled_out: 'neutral',
};

export function DiagnosesTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const confirm = useConfirm();
  const toast = useToast();
  const invalidate = useInvalidate();
  const id = bundle.patient.id;

  const remove = useAction((diagnosisId: number) => del(`/api/patients/${id}/diagnoses/${diagnosisId}`), {
    invalidate: [['patient', String(id)]],
  });

  const form = useForm({
    initial: { system: 'icd10', code: '', title: '', status: 'active', onset_date: '', notes: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.code.trim()) errors.code = 'Escribe el código.';
      if (!values.title.trim()) errors.title = 'Escribe la descripción.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post(`/api/patients/${id}/diagnoses`, values);
      toast.success(result.message ?? 'Diagnóstico agregado.');
      form.setValues({ system: values.system, code: '', title: '', status: 'active', onset_date: '', notes: '' });
      await invalidate(['patient', String(id)]);
    },
  });

  const onDelete = async (diagnosis: Diagnosis) => {
    const ok = await confirm({
      title: 'Eliminar diagnóstico',
      text: `Se eliminará ${diagnosis.code} · ${diagnosis.title}. Si el diagnóstico cambió, considera marcarlo como resuelto o descartado en su lugar.`,
      confirmLabel: 'Eliminar',
      danger: true,
    });
    if (ok) await remove.run(diagnosis.id).catch(() => undefined);
  };

  return (
    <div className="layout-aside">
      <Panel title="Diagnósticos" titleId="diagnosticos" flush>
        {bundle.diagnoses.length === 0 ? (
          <EmptyState icon="clipboard" title="Sin diagnósticos registrados" />
        ) : (
          <ul className="list">
            {bundle.diagnoses.map((diagnosis) => (
              <li key={diagnosis.id} className="list__item">
                <span className="list__main">
                  <span className="list__title">
                    {diagnosis.code} · {diagnosis.title}
                  </span>
                  <span className="list__meta">
                    {diagnosis.system === 'dsm5' ? 'DSM-5' : 'CIE-10'}
                    {diagnosis.onset_date && ` · desde ${formatDate(diagnosis.onset_date)}`}
                    {diagnosis.notes && ` · ${diagnosis.notes}`}
                  </span>
                </span>
                <Badge tone={STATUS_TONE[diagnosis.status]}>{labelOf(meta?.diagnosisStatuses, diagnosis.status)}</Badge>
                <Button size="sm" variant="quiet" iconOnly icon="trash" aria-label={`Eliminar ${diagnosis.code}`} onClick={() => onDelete(diagnosis)} />
              </li>
            ))}
          </ul>
        )}
      </Panel>

      <Panel title="Agregar diagnóstico" titleId="nuevo-diagnostico">
        <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
          <FormAlert message={form.formError} />
          <SelectField label="Sistema" options={SYSTEMS} {...form.bind('system')} />
          <TextField label="Código" placeholder="F41.1" {...form.bind('code')} />
          <TextField label="Descripción" {...form.bind('title')} />
          <SelectField label="Estado" options={meta?.diagnosisStatuses ?? []} {...form.bind('status')} />
          <TextField label="Fecha de inicio" type="date" optional {...form.bind('onset_date')} />
          <TextAreaField label="Observaciones" optional rows={2} {...form.bind('notes')} />
          <div>
            <Button type="submit" variant="primary" loading={form.submitting}>
              Agregar
            </Button>
          </div>
        </form>
      </Panel>
    </div>
  );
}
