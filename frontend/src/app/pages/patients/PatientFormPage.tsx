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

const DOCUMENT_TYPES = [
  { value: 'CC', label: 'Cédula de ciudadanía' },
  { value: 'TI', label: 'Tarjeta de identidad' },
  { value: 'CE', label: 'Cédula de extranjería' },
  { value: 'PA', label: 'Pasaporte' },
  { value: 'RC', label: 'Registro civil' },
];

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
      if (!values.first_name.trim()) errors.first_name = 'Escribe el nombre.';
      if (!values.last_name.trim()) errors.last_name = 'Escribe los apellidos.';
      if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) errors.email = 'Revisa el formato del correo.';
      return errors;
    },
    onSubmit: async (values) => {
      if (patient) {
        const result = await put(`/api/patients/${patient.id}`, values);
        toast.success(result.message ?? 'Cambios guardados.');
        await invalidate(['patient', String(patient.id)], ['patients']);
        navigate(`/app/pacientes/${patient.id}`);
      } else {
        const result = await post<{ id: number }>('/api/patients', values);
        toast.success(result.message ?? 'Paciente registrado.');
        await invalidate(['patients'], ['meta'], ['dashboard']);
        navigate(`/app/pacientes/${result.data.id}`);
      }
    },
  });

  const cancelTo = patient ? `/app/pacientes/${patient.id}` : '/app/pacientes';

  return (
    <form onSubmit={form.handleSubmit} noValidate>
      <Panel>
        <FormAlert message={form.formError} />

        <section className="form-section">
          <h2 className="form-section__title">Identificación</h2>
          <p className="form-section__intro">
            {patient ? `Historia ${patient.record_number}.` : `Se asignará el número ${meta?.nextRecordNumber ?? '…'} al guardar.`}
          </p>
          <div className="form-grid">
            <TextField label="Nombres" autoComplete="off" {...form.bind('first_name')} />
            <TextField label="Apellidos" autoComplete="off" {...form.bind('last_name')} />
            <TextField label="Fecha de nacimiento" type="date" optional {...form.bind('birth_date')} />
            <SelectField label="Género" options={meta?.genders ?? []} {...form.bind('gender')} />
            <SelectField label="Tipo de documento" options={DOCUMENT_TYPES} {...form.bind('document_type')} />
            <TextField label="Número de documento" optional inputMode="numeric" {...form.bind('document_id')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Contacto</h2>
          <p className="form-section__intro">El correo es necesario para crear el acceso al portal del paciente.</p>
          <div className="form-grid">
            <TextField label="Correo" type="email" optional {...form.bind('email')} />
            <TextField label="Teléfono" type="tel" optional {...form.bind('phone')} />
            <TextField label="Dirección" optional wrapperClassName="span-full" {...form.bind('address')} />
            <TextField label="Ciudad" optional {...form.bind('city')} />
            <TextField label="País" optional {...form.bind('country')} />
            <TextField label="Ocupación" optional {...form.bind('occupation')} />
            <TextField label="Estado civil" optional {...form.bind('marital_status')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Contacto de emergencia</h2>
          <p className="form-section__intro">A quién llamar si es necesario durante el proceso.</p>
          <div className="form-grid">
            <TextField label="Nombre" optional {...form.bind('emergency_contact_name')} />
            <TextField label="Teléfono" type="tel" optional {...form.bind('emergency_contact_phone')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Información clínica</h2>
          <p className="form-section__intro">Solo la ve el equipo clínico. El nivel de riesgo se actualiza también desde cada nota de sesión.</p>
          <div className="form-grid">
            <TextAreaField label="Motivo de consulta" optional rows={3} wrapperClassName="span-full" {...form.bind('reason_for_consult')} />
            <TextAreaField label="Antecedentes relevantes" optional rows={3} wrapperClassName="span-full" {...form.bind('relevant_history')} />
            <TextAreaField label="Medicación actual" optional rows={2} {...form.bind('current_medication')} />
            <TextField label="Remitido por" optional {...form.bind('referred_by')} />
            <SelectField label="Nivel de riesgo" options={meta?.riskLevels ?? []} {...form.bind('risk_level')} />
            <SelectField label="Estado del proceso" options={meta?.patientStatuses ?? []} {...form.bind('status')} />
            <SelectField
              label="Profesional a cargo"
              placeholder="Sin asignar"
              options={(meta?.psychologists ?? []).map((person) => ({ value: String(person.id), label: person.full_name }))}
              {...form.bind('psychologist_id')}
            />
          </div>
        </section>

        <div className="form-actions">
          <ButtonLink to={cancelTo} variant="quiet">
            Cancelar
          </ButtonLink>
          <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Guardando">
            {patient ? 'Guardar cambios' : 'Registrar paciente'}
          </Button>
        </div>
      </Panel>
    </form>
  );
}

export default function PatientFormPage() {
  const { id } = useParams();
  const editing = Boolean(id);
  useDocumentTitle(editing ? 'Editar paciente' : 'Nuevo paciente');

  const query = useQuery({
    queryKey: ['patient', id],
    queryFn: () => get<{ patient: Patient }>(`/api/patients/${id}`),
    enabled: editing,
  });

  return (
    <>
      <PageHeader
        back={editing ? { to: `/app/pacientes/${id}`, label: 'Volver a la ficha' } : { to: '/app/pacientes', label: 'Pacientes' }}
        title={editing ? 'Editar paciente' : 'Nuevo paciente'}
        subtitle={editing ? undefined : 'Solo los nombres son obligatorios. El resto puedes completarlo más adelante.'}
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
