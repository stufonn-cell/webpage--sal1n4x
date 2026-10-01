import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { SelectField } from '@/components/ui/Field';
import { post } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import { useAction } from '../useAction';

/** Creates an informed consent from a template, in English or Spanish, ready to be signed. */
export function ConsentCreate({ patientId }: { patientId?: number }) {
  const { data: meta } = useMeta();
  const [patient, setPatient] = useState(patientId ? String(patientId) : '');
  const [template, setTemplate] = useState('');
  const [chosenLanguage, setLanguage] = useState('');
  // Until someone picks one, the practice default from Settings applies.
  const language = chosenLanguage || meta?.settings.document_language || 'en';

  const create = useAction(
    () => post('/api/consents', { patient_id: Number(patient), template_code: template, language }),
    {
      invalidate: [['consents'], ['patient', patient]],
      onSuccess: () => setTemplate(''),
    },
  );

  return (
    <div className="inline-form">
      {!patientId && (
        <SelectField
          label="Patient"
          id="consent-patient"
          placeholder="Choose a patient"
          value={patient}
          onChange={(event) => setPatient(event.target.value)}
          options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
        />
      )}
      <SelectField
        label="Template"
        id="consent-template"
        placeholder="Choose a template"
        value={template}
        onChange={(event) => setTemplate(event.target.value)}
        options={meta?.consentTemplates ?? []}
      />
      <SelectField
        label="Document language"
        id="consent-language"
        hint="The patient reads and signs the consent in this language."
        value={language}
        onChange={(event) => setLanguage(event.target.value)}
        options={meta?.documentLanguages ?? [{ value: 'en', label: 'English' }]}
      />
      <div>
        <Button
          variant="primary"
          icon="clipboard"
          disabled={!patient || !template}
          loading={create.pending}
          onClick={() => create.run(undefined).catch(() => undefined)}
        >
          Create consent
        </Button>
      </div>
      <p className="xsmall muted">The patient can read and sign it from their portal, or you can sign it together during the session.</p>
    </div>
  );
}
