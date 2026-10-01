import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { useConfirm, useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useForm } from '@/hooks/useForm';
import { del, patch, post } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { Icd11Code } from '@/lib/types';
import { Icd11Picker } from '../../../components/Icd11Picker';
import { useAction, useInvalidate } from '../../../useAction';
import type { Diagnosis, PatientBundle } from './types';

const STATUS_TONE: Record<string, 'warning' | 'info' | 'success' | 'neutral'> = {
  active: 'warning',
  remission: 'info',
  resolved: 'success',
  ruled_out: 'neutral',
};

const SYSTEM_LABEL: Record<Diagnosis['system'], string> = { icd11: 'ICD-11', icd10: 'ICD-10', dsm5: 'DSM-5' };

/** Same rule the backend applies: RIPS takes ICD-10 without the dot and with 4 or 5 characters. */
function ripsCode(code: string | null | undefined): string | null {
  const normalized = (code ?? '').toUpperCase().replace(/[.\s]/g, '');
  return /^[A-Z]\d{2}[0-9A-Z]{1,2}$/.test(normalized) ? normalized : null;
}

function ripsHint(code: string): string {
  if (!code.trim()) return 'RIPS reports ICD-10. Without it, this diagnosis cannot be reported.';
  const rips = ripsCode(code);
  return rips ? `RIPS will receive ${rips}.` : 'RIPS needs the exact 4-character subcategory, e.g. F32.1 instead of F32.';
}

/** Reportable diagnoses are the active ones and those in remission. */
const reportable = (diagnosis: Diagnosis) => diagnosis.status === 'active' || diagnosis.status === 'remission';

function DiagnosisEditor({ patientId, diagnosis, onClose }: { patientId: number; diagnosis: Diagnosis; onClose: () => void }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const [primary, setPrimary] = useState(diagnosis.is_primary);
  const form = useForm({
    initial: { status: diagnosis.status, icd10_code: diagnosis.icd10_code ?? '' },
    onSubmit: async (values) => {
      const body: Record<string, unknown> = { status: values.status, is_primary: primary && !diagnosis.is_primary };
      if (diagnosis.system !== 'icd10') body.icd10_code = values.icd10_code;
      const result = await patch(`/api/patients/${patientId}/diagnoses/${diagnosis.id}`, body);
      toast.success(result.message ?? 'Diagnosis updated.');
      await invalidate(['patient', String(patientId)]);
      onClose();
    },
  });

  return (
    <form className="inline-form diagnosis-editor" onSubmit={form.handleSubmit} noValidate aria-label={`Edit ${diagnosis.code}`}>
      <FormAlert message={form.formError} />
      <SelectField label="Status" options={meta?.diagnosisStatuses ?? []} {...form.bind('status')} id={`status-${diagnosis.id}`} />
      {diagnosis.system !== 'icd10' && (
        <TextField
          label="ICD-10 equivalent"
          optional
          placeholder="F41.1"
          hint={ripsHint(form.values.icd10_code)}
          {...form.bind('icd10_code')}
          id={`icd10-${diagnosis.id}`}
        />
      )}
      {!diagnosis.is_primary && (
        <label className="check">
          <input type="checkbox" checked={primary} onChange={(event) => setPrimary(event.target.checked)} />
          <span>Make it the primary diagnosis (the one RIPS reports first)</span>
        </label>
      )}
      <div className="cluster">
        <Button type="submit" variant="primary" size="sm" loading={form.submitting}>
          Save
        </Button>
        <Button size="sm" variant="quiet" onClick={onClose}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

const EMPTY = { system: 'icd11', code: '', title: '', icd10_code: '', status: 'active', onset_date: '', notes: '' };

function NewDiagnosisForm({ patientId, hasDiagnoses }: { patientId: number; hasDiagnoses: boolean }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const [picked, setPicked] = useState<Icd11Code | null>(null);
  const [primary, setPrimary] = useState(false);

  const form = useForm({
    initial: EMPTY,
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (values.system === 'icd11') {
        if (!picked) errors.code = 'Search and pick an ICD-11 code.';
      } else {
        if (!values.code.trim()) errors.code = 'Enter the code.';
        if (!values.title.trim()) errors.title = 'Enter the description.';
      }
      return errors;
    },
    onSubmit: async (values) => {
      const body = {
        ...values,
        code: values.system === 'icd11' ? (picked?.code ?? '') : values.code,
        title: values.system === 'icd11' ? (picked?.title ?? '') : values.title,
        icd10_code: values.system === 'icd10' ? '' : values.icd10_code,
        is_primary: primary,
      };
      const result = await post(`/api/patients/${patientId}/diagnoses`, body);
      toast.success(result.message ?? 'Diagnosis added.');
      form.setValues({ ...EMPTY, system: values.system });
      setPicked(null);
      setPrimary(false);
      await invalidate(['patient', String(patientId)]);
    },
  });

  const system = form.values.system;

  const changeSystem = (value: string) => {
    form.setValues({ ...EMPTY, system: value, status: form.values.status, onset_date: form.values.onset_date, notes: form.values.notes });
    form.setErrors({});
    setPicked(null);
  };

  const pick = (code: Icd11Code | null) => {
    setPicked(code);
    form.set('icd10_code', code?.icd10_code ?? '');
    form.setErrors({});
  };

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <SelectField
        label="Classification"
        options={meta?.diagnosisSystems ?? [{ value: 'icd11', label: 'ICD-11' }]}
        {...form.bind('system')}
        onChange={(event) => changeSystem(event.target.value)}
      />

      {system === 'icd11' ? (
        <>
          <Icd11Picker id="code" label="ICD-11 code" value={picked} onChange={pick} error={form.errors.code} release={meta?.icd11Release} />
          {picked && (
            <TextField
              label="ICD-10 equivalent"
              optional
              placeholder="F41.1"
              hint={picked.icd10_code ? `WHO equivalent: ${picked.icd10_code}. ${ripsHint(form.values.icd10_code)}` : ripsHint(form.values.icd10_code)}
              {...form.bind('icd10_code')}
            />
          )}
        </>
      ) : (
        <>
          <TextField label="Code" placeholder="F41.1" {...form.bind('code')} />
          <TextField label="Description" {...form.bind('title')} />
          {system === 'icd10' ? (
            <p className="field__hint">{ripsHint(form.values.code)}</p>
          ) : (
            <TextField label="ICD-10 equivalent" optional placeholder="F41.1" hint={ripsHint(form.values.icd10_code)} {...form.bind('icd10_code')} />
          )}
        </>
      )}

      <SelectField label="Status" options={meta?.diagnosisStatuses ?? []} {...form.bind('status')} />
      <TextField label="Onset date" type="date" optional {...form.bind('onset_date')} />
      <TextAreaField label="Notes" optional rows={2} {...form.bind('notes')} />
      {hasDiagnoses ? (
        <label className="check">
          <input type="checkbox" checked={primary} onChange={(event) => setPrimary(event.target.checked)} />
          <span>Primary diagnosis</span>
        </label>
      ) : (
        <p className="field__hint">The first diagnosis becomes the primary one.</p>
      )}
      <div>
        <Button type="submit" variant="primary" loading={form.submitting}>
          Add diagnosis
        </Button>
      </div>
    </form>
  );
}

export function DiagnosesTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const confirm = useConfirm();
  const [editing, setEditing] = useState<number | null>(null);
  const id = bundle.patient.id;

  const remove = useAction((diagnosisId: number) => del(`/api/patients/${id}/diagnoses/${diagnosisId}`), {
    invalidate: [['patient', String(id)]],
  });

  const onDelete = async (diagnosis: Diagnosis) => {
    const ok = await confirm({
      title: 'Delete diagnosis',
      text: `${diagnosis.code} · ${diagnosis.title} will be deleted. If the diagnosis changed, consider marking it as resolved or ruled out instead.`,
      confirmLabel: 'Delete',
      danger: true,
    });
    if (ok) await remove.run(diagnosis.id).catch(() => undefined);
  };

  return (
    <div className="layout-aside">
      <Panel title="Diagnoses" titleId="diagnoses-title" flush>
        {bundle.diagnoses.length === 0 ? (
          <EmptyState icon="clipboard" title="No diagnoses recorded" text="RIPS needs at least one active diagnosis to report this patient's consultations." />
        ) : (
          <ul className="list">
            {bundle.diagnoses.map((diagnosis) => {
              const missingRips = reportable(diagnosis) && diagnosis.rips_code === null;
              return (
                <li key={diagnosis.id} className="list__item diagnosis">
                  <div className="diagnosis__row">
                    <span className="list__main">
                      <span className="list__title">
                        {diagnosis.code} · {diagnosis.title}
                      </span>
                      <span className="list__meta">
                        {SYSTEM_LABEL[diagnosis.system]}
                        {diagnosis.system !== 'icd10' && diagnosis.icd10_code && ` · ICD-10 ${diagnosis.icd10_code}`}
                        {diagnosis.onset_date && ` · since ${formatDate(diagnosis.onset_date)}`}
                        {diagnosis.notes && ` · ${diagnosis.notes}`}
                      </span>
                      {missingRips && (
                        <span className="diagnosis__warning">
                          <Icon name="alert" size={14} />
                          {diagnosis.icd10_code
                            ? `RIPS needs the 4-character ICD-10 subcategory (currently ${diagnosis.icd10_code}).`
                            : 'Add the ICD-10 equivalent so RIPS can report it.'}
                        </span>
                      )}
                    </span>
                    {diagnosis.is_primary && <Badge tone="primary">Primary</Badge>}
                    <Badge tone={STATUS_TONE[diagnosis.status]}>{labelOf(meta?.diagnosisStatuses, diagnosis.status)}</Badge>
                    <Button
                      size="sm"
                      variant="quiet"
                      iconOnly
                      icon="edit"
                      aria-label={`Edit ${diagnosis.code}`}
                      aria-expanded={editing === diagnosis.id}
                      onClick={() => setEditing(editing === diagnosis.id ? null : diagnosis.id)}
                    />
                    <Button size="sm" variant="quiet" iconOnly icon="trash" aria-label={`Delete ${diagnosis.code}`} onClick={() => onDelete(diagnosis)} />
                  </div>
                  {editing === diagnosis.id && <DiagnosisEditor patientId={id} diagnosis={diagnosis} onClose={() => setEditing(null)} />}
                </li>
              );
            })}
          </ul>
        )}
      </Panel>

      <Panel title="Add diagnosis" titleId="new-diagnosis">
        <NewDiagnosisForm patientId={id} hasDiagnoses={bundle.diagnoses.length > 0} />
      </Panel>
    </div>
  );
}
