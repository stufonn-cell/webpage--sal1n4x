import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { SelectField } from '@/components/ui/Field';
import { post } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import { useAction } from '../useAction';

/** Genera un consentimiento desde una plantilla y lo deja listo para firmar. */
export function ConsentCreate({ patientId }: { patientId?: number }) {
  const { data: meta } = useMeta();
  const [patient, setPatient] = useState(patientId ? String(patientId) : '');
  const [template, setTemplate] = useState('');

  const create = useAction(
    () => post('/api/consents', { patient_id: Number(patient), template_code: template }),
    {
      invalidate: [['consents'], ['patient', patient]],
      onSuccess: () => setTemplate(''),
    },
  );

  return (
    <div className="inline-form">
      {!patientId && (
        <SelectField
          label="Paciente"
          id="consent-patient"
          placeholder="Elige un paciente"
          value={patient}
          onChange={(event) => setPatient(event.target.value)}
          options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
        />
      )}
      <SelectField
        label="Plantilla"
        id="consent-template"
        placeholder="Elige una plantilla"
        value={template}
        onChange={(event) => setTemplate(event.target.value)}
        options={meta?.consentTemplates ?? []}
      />
      <div>
        <Button
          variant="primary"
          icon="clipboard"
          disabled={!patient || !template}
          loading={create.pending}
          onClick={() => create.run(undefined).catch(() => undefined)}
        >
          Generar consentimiento
        </Button>
      </div>
      <p className="xsmall muted">El paciente podrá leerlo y firmarlo desde su portal, o puedes firmarlo con él en consulta.</p>
    </div>
  );
}
