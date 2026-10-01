import { useQuery } from '@tanstack/react-query';
import { useLocation, useNavigate, useParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, post, put } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import type { Patient } from '@/lib/types';
import { useInvalidate } from '../../useAction';

const EMPTY = {
  first_name: '',
  last_name: '',
  birth_date: '',
  gender: 'undisclosed',
  document_type: 'CC',
  document_id: '',
  biological_sex: '',
  rips_user_type: '12',
  residence_country: '170',
  residence_municipality: '',
  residence_zone: '01',
  origin_country: '170',
  email: '',
  phone: '',
  address: '',
  city: '',
  country: 'Colombia',
  occupation: '',
  marital_status: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  referred_by: '',
  reason_for_consult: '',
  relevant_history: '',
  current_medication: '',
  risk_level: 'none',
  status: 'active',
  psychologist_id: '',
};

type Values = typeof EMPTY;

function toValues(patient: Patient): Values {
  const values = { ...EMPTY };
  for (const key of Object.keys(EMPTY) as (keyof Values)[]) {
    const raw = patient[key as keyof Patient];
    values[key] = raw === null || raw === undefined ? '' : String(raw);
  }
  return values;
}

function PatientForm({ patient }: { patient?: Patient }) {
  const { data: meta } = useMeta();
  const navigate = useNavigate();
  const location = useLocation();
  const toast = useToast();
  const invalidate = useInvalidate();
  const prefill = (location.state as { prefill?: { full_name?: string; email?: string; phone?: string } } | null)?.prefill;

  const splitName = (name = '') => {
    const parts = name.trim().split(/\s+/);
    return { first_name: parts.slice(0, Math.ceil(parts.length / 2)).join(' '), last_name: parts.slice(Math.ceil(parts.length / 2)).join(' ') };
  };

  const initial: Values = patient
    ? toValues(patient)
    : {
        ...EMPTY,
        ...(prefill?.full_name ? splitName(String(prefill.full_name)) : {}),
        email: prefill?.email ?? '',
        phone: prefill?.phone ?? '',
      };

  const form = useForm<Values>({
    initial,
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.first_name.trim()) errors.first_name = 'Enter the first name.';
      if (!values.last_name.trim()) errors.last_name = 'Enter the last name.';
      if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) errors.email = 'Check the email format.';
      if (values.residence_municipality && !/^\d{5}$/.test(values.residence_municipality)) {
        errors.residence_municipality = 'Use the 5-digit DIVIPOLA code, e.g. 11001.';
      }
      for (const field of ['residence_country', 'origin_country'] as const) {
        if (values[field] && !/^\d{3}$/.test(values[field])) errors[field] = 'Use the 3-digit numeric code (170 for Colombia).';
      }
      return errors;
    },
    onSubmit: async (values) => {
      if (patient) {
        const result = await put(`/api/patients/${patient.id}`, values);
        toast.success(result.message ?? 'Changes saved.');
        await invalidate(['patient', String(patient.id)], ['patients']);
        navigate(`/app/patients/${patient.id}`);
      } else {
        const result = await post<{ id: number }>('/api/patients', values);
        toast.success(result.message ?? 'Patient registered.');
        await invalidate(['patients'], ['meta'], ['dashboard']);
        navigate(`/app/patients/${result.data.id}`);
      }
    },
  });

  const cancelTo = patient ? `/app/patients/${patient.id}` : '/app/patients';
  const livesInColombia = form.values.residence_country === '170' || form.values.residence_country === '';

  return (
    <form onSubmit={form.handleSubmit} noValidate>
      <Panel>
        <FormAlert message={form.formError} />

        <section className="form-section">
          <h2 className="form-section__title">Identification</h2>
          <p className="form-section__intro">
            {patient ? `Record ${patient.record_number}.` : `Record number ${meta?.nextRecordNumber ?? '…'} will be assigned when you save.`}
          </p>
          <div className="form-grid">
            <TextField label="First name" autoComplete="off" {...form.bind('first_name')} />
            <TextField label="Last name" autoComplete="off" {...form.bind('last_name')} />
            <TextField label="Date of birth" type="date" optional {...form.bind('birth_date')} />
            <SelectField label="Gender" options={meta?.genders ?? []} {...form.bind('gender')} />
            <SelectField label="Document type" options={meta?.rips.documentTypes ?? []} {...form.bind('document_type')} />
            <TextField label="Document number" optional inputMode="numeric" {...form.bind('document_id')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">RIPS details</h2>
          <p className="form-section__intro">
            Needed to report this patient's consultations to the Ministry of Health (RIPS, Resolution 2275 of 2023). You can
            complete them later; the RIPS screen lists anything missing.
          </p>
          <div className="form-grid">
            <SelectField
              label="Sex on the ID document"
              placeholder="Not recorded"
              hint="As it appears on the identity document, which may differ from gender."
              options={meta?.rips.sexes ?? []}
              {...form.bind('biological_sex')}
            />
            <SelectField label="Health coverage" options={meta?.rips.userTypes ?? []} {...form.bind('rips_user_type')} />
            <TextField
              label="Country of residence"
              inputMode="numeric"
              maxLength={3}
              hint="3-digit numeric code. 170 is Colombia."
              {...form.bind('residence_country')}
            />
            {livesInColombia && (
              <TextField
                label="Municipality of residence"
                optional
                inputMode="numeric"
                maxLength={5}
                placeholder="11001"
                hint="5-digit DIVIPOLA code, e.g. 11001 Bogota, 05001 Medellin, 76001 Cali."
                {...form.bind('residence_municipality')}
              />
            )}
            <SelectField label="Area of residence" options={meta?.rips.zones ?? []} {...form.bind('residence_zone')} />
            <TextField label="Country of origin" inputMode="numeric" maxLength={3} hint="3-digit numeric code. 170 is Colombia." {...form.bind('origin_country')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Contact</h2>
          <p className="form-section__intro">The email is needed to create the patient's portal access.</p>
          <div className="form-grid">
            <TextField label="Email" type="email" optional {...form.bind('email')} />
            <TextField label="Phone" type="tel" optional {...form.bind('phone')} />
            <TextField label="Address" optional wrapperClassName="span-full" {...form.bind('address')} />
            <TextField label="City" optional {...form.bind('city')} />
            <TextField label="Country" optional {...form.bind('country')} />
            <TextField label="Occupation" optional {...form.bind('occupation')} />
            <TextField label="Marital status" optional {...form.bind('marital_status')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Emergency contact</h2>
          <p className="form-section__intro">Who to call if needed during the process.</p>
          <div className="form-grid">
            <TextField label="Name" optional {...form.bind('emergency_contact_name')} />
            <TextField label="Phone" type="tel" optional {...form.bind('emergency_contact_phone')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Clinical information</h2>
          <p className="form-section__intro">Only the clinical team can see it. The risk level is also updated from each session note.</p>
          <div className="form-grid">
            <TextAreaField label="Reason for consultation" optional rows={3} wrapperClassName="span-full" {...form.bind('reason_for_consult')} />
            <TextAreaField label="Relevant history" optional rows={3} wrapperClassName="span-full" {...form.bind('relevant_history')} />
            <TextAreaField label="Current medication" optional rows={2} {...form.bind('current_medication')} />
            <TextField label="Referred by" optional {...form.bind('referred_by')} />
            <SelectField label="Risk level" options={meta?.riskLevels ?? []} {...form.bind('risk_level')} />
            <SelectField label="Process status" options={meta?.patientStatuses ?? []} {...form.bind('status')} />
            <SelectField
              label="Professional in charge"
              placeholder="Unassigned"
              options={(meta?.psychologists ?? []).map((person) => ({ value: String(person.id), label: person.full_name }))}
              {...form.bind('psychologist_id')}
            />
          </div>
        </section>

        <div className="form-actions">
          <ButtonLink to={cancelTo} variant="quiet">
            Cancel
          </ButtonLink>
          <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Saving">
            {patient ? 'Save changes' : 'Register patient'}
          </Button>
        </div>
      </Panel>
    </form>
  );
}

export default function PatientFormPage() {
  const { id } = useParams();
  const editing = Boolean(id);
  useDocumentTitle(editing ? 'Edit patient' : 'New patient');

  const query = useQuery({
    queryKey: ['patient', id],
    queryFn: () => get<{ patient: Patient }>(`/api/patients/${id}`),
    enabled: editing,
  });

  return (
    <>
      <PageHeader
        back={editing ? { to: `/app/patients/${id}`, label: 'Back to the record' } : { to: '/app/patients', label: 'Patients' }}
        title={editing ? 'Edit patient' : 'New patient'}
        subtitle={editing ? undefined : 'Only the names are required. You can fill in the rest later.'}
      />
      {editing ? (
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data && <PatientForm patient={query.data.patient} />}
        </QueryState>
      ) : (
        <PatientForm />
      )}
    </>
  );
}
