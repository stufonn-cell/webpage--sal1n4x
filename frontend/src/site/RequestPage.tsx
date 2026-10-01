import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Progress } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle, useMetaDescription } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { post, type FieldErrors } from '@/lib/api';
import type { Option } from '@/lib/types';
import { SERVICES } from './content';
import { telLink, useSite } from './useSite';

interface RequestValues extends Record<string, unknown> {
  attendee: string;
  modality: string;
  preferred_times: string[];
  preferred_professional_id: string;
  message: string;
  full_name: string;
  email: string;
  phone: string;
  contact_preference: string;
  privacy_accepted: boolean;
  website: string;
}

const STEP_FIELDS: (keyof RequestValues)[][] = [
  ['attendee', 'modality'],
  ['preferred_times', 'preferred_professional_id', 'message'],
  ['full_name', 'email', 'phone', 'contact_preference', 'privacy_accepted'],
];

const STEP_TITLES = ['¿Para quién es la cita?', '¿Cuándo te queda mejor?', '¿Cómo te contactamos?'];

const ATTENDEE_HINTS: Record<string, string> = {
  self: 'Quieres empezar un proceso propio.',
  minor: 'Eres madre, padre o representante legal.',
  other: 'Pides información en nombre de alguien.',
};

const MODALITY_HINTS: Record<string, string> = {
  in_person: 'En el consultorio.',
  online: 'Por videollamada o teléfono.',
  no_preference: 'Lo decidimos juntos.',
};

function validateStep(step: number, values: RequestValues): FieldErrors {
  const errors: FieldErrors = {};
  if (step === 0) {
    if (!values.attendee) errors.attendee = 'Elige una opción.';
    if (!values.modality) errors.modality = 'Elige una opción.';
  }
  if (step === 2) {
    if (!values.full_name.trim()) errors.full_name = 'Escribe tu nombre.';
    if (!values.email.trim()) errors.email = 'Escribe tu correo.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) errors.email = 'Revisa el correo; parece incompleto.';
    if (['phone', 'whatsapp'].includes(values.contact_preference) && !values.phone.trim()) {
      errors.phone = 'Déjanos un número para poder llamarte o escribirte.';
    }
    if (!values.privacy_accepted) errors.privacy_accepted = 'Necesitamos tu autorización para contactarte.';
  }
  return errors;
}

function ChoiceGroup({
  name,
  legend,
  options,
  value,
  hints,
  error,
  onChange,
}: {
  name: string;
  legend: string;
  options: Option[];
  value: string;
  hints: Record<string, string>;
  error?: string;
  onChange: (value: string) => void;
}) {
  return (
    <fieldset className="request__group" aria-describedby={error ? `${name}-error` : undefined}>
      <legend className="field__label">{legend}</legend>
      <div className="choice-grid">
        {options.map((option, index) => (
          <label key={option.value} className="choice">
            <input
              type="radio"
              name={name}
              id={index === 0 ? name : undefined}
              value={option.value}
              checked={value === option.value}
              onChange={() => onChange(option.value)}
            />
            <span>
              <span className="choice__title">{option.label}</span>
              {hints[option.value] && <span className="choice__hint">{hints[option.value]}</span>}
            </span>
          </label>
        ))}
      </div>
      {error && (
        <span className="field__error" id={`${name}-error`} role="alert">
          {error}
        </span>
      )}
    </fieldset>
  );
}

export default function RequestPage() {
  const { data: site, isPending } = useSite();
  const [params] = useSearchParams();
  const [step, setStep] = useState(0);
  const [sentName, setSentName] = useState<string | null>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);
  const firstRender = useRef(true);

  const service = SERVICES.find((item) => item.id === params.get('servicio'));
  useDocumentTitle('Solicitar una cita', site?.clinic.clinic_name);
  useMetaDescription('Solicita una primera cita de atención psicológica, presencial o virtual. Te contactamos para acordar el horario.');

  const form = useForm<RequestValues>({
    initial: {
      attendee: service?.id === 'infancia' ? 'minor' : '',
      modality: service?.id === 'virtual' ? 'online' : '',
      preferred_times: [],
      preferred_professional_id: '',
      message: service ? `Me interesa: ${service.title}.` : '',
      full_name: '',
      email: '',
      phone: '',
      contact_preference: 'email',
      privacy_accepted: false,
      website: '',
    },
    validate: (values) => validateStep(2, values),
    onSubmit: async (values) => {
      await post('/api/public/appointment-requests', {
        ...values,
        preferred_professional_id: values.preferred_professional_id ? Number(values.preferred_professional_id) : null,
      });
      setSentName(values.full_name.trim().split(/\s+/)[0] ?? '');
    },
  });

  // Si la API devuelve errores de un paso anterior, se vuelve a ese paso.
  useEffect(() => {
    const fields = Object.keys(form.errors);
    if (fields.length === 0) return;
    const stepWithError = STEP_FIELDS.findIndex((group) => group.some((field) => fields.includes(field as string)));
    if (stepWithError >= 0 && stepWithError < step) setStep(stepWithError);
  }, [form.errors, step]);

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;
      return;
    }
    headingRef.current?.focus();
  }, [step, sentName]);

  const next = () => {
    const errors = validateStep(step, form.values);
    if (Object.keys(errors).length > 0) {
      form.setErrors(errors);
      document.getElementById(Object.keys(errors)[0])?.focus();
      return;
    }
    setStep((current) => current + 1);
  };

  const toggleTime = (value: string) => {
    const current = form.values.preferred_times;
    form.set('preferred_times', current.includes(value) ? current.filter((item) => item !== value) : [...current, value]);
  };

  const crisisLine = site?.clinic.crisis_line || '123';

  if (sentName !== null) {
    return (
      <section className="request request--done" aria-labelledby="request-done-title">
        <div className="container container--narrow">
          <div className="request__done enter">
            <span className="request__done-icon" aria-hidden="true">
              <Icon name="check" size={26} />
            </span>
            <h1 className="request__title serif" id="request-done-title" ref={headingRef} tabIndex={-1}>
              Gracias{sentName ? `, ${sentName}` : ''}. Recibimos tu solicitud.
            </h1>
            <p className="request__lead">
              Pedir ayuda no siempre es fácil, y ya diste el primer paso. Una persona de nuestro equipo revisará tu solicitud y
              se comunicará contigo por el medio que elegiste para acordar día y hora.
            </p>
            <Alert tone="info" title="Si necesitas ayuda inmediata">
              Esta solicitud no es una cita de urgencia. Si estás en peligro, llama a la línea de emergencias{' '}
              <a href={telLink(crisisLine)}>{crisisLine}</a>.
            </Alert>
            <div className="cluster">
              <ButtonLink to="/" variant="primary">
                Volver al inicio
              </ButtonLink>
              <ButtonLink to="/#preguntas" variant="quiet">
                Leer preguntas frecuentes
              </ButtonLink>
            </div>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="request" aria-labelledby="request-title">
      <div className="container container--narrow">
        <header className="request__header enter">
          <p className="eyebrow">Solicitud de cita</p>
          <h1 className="request__title serif" id="request-title">
            Cuéntanos un poco y te contactamos
          </h1>
          <p className="request__lead">
            Son tres pasos cortos. No necesitas contar aquí los motivos de la consulta: eso lo conversaremos con calma.
          </p>
        </header>

        <div className="request__progress" aria-live="polite">
          <div className="split">
            <span className="small">
              Paso {step + 1} de 3 · <strong>{STEP_TITLES[step]}</strong>
            </span>
          </div>
          <Progress value={step + 1} max={3} label="Avance de la solicitud" />
        </div>

        <form
          className="request__card"
          noValidate
          onSubmit={(event) => {
            // Enter en los primeros pasos avanza en lugar de enviar.
            if (step < 2) {
              event.preventDefault();
              next();
              return;
            }
            void form.handleSubmit(event);
          }}
        >
          <h2 className="request__step-title" ref={headingRef} tabIndex={-1}>
            {STEP_TITLES[step]}
          </h2>

          <FormAlert message={form.formError} />

          {/* Campo trampa para bots: oculto a personas y a lectores de pantalla. */}
          <div className="visually-hidden" aria-hidden="true">
            <label htmlFor="website">No completes este campo</label>
            <input
              id="website"
              name="website"
              tabIndex={-1}
              autoComplete="off"
              value={form.values.website}
              onChange={(event) => form.set('website', event.target.value)}
            />
          </div>

          {step === 0 && (
            <div className="request__step" key="step-0">
              <ChoiceGroup
                name="attendee"
                legend="La cita es…"
                options={site?.form.attendees ?? []}
                value={form.values.attendee}
                hints={ATTENDEE_HINTS}
                error={form.errors.attendee}
                onChange={(value) => form.set('attendee', value)}
              />
              <ChoiceGroup
                name="modality"
                legend="¿Cómo prefieres la atención?"
                options={site?.form.modalities ?? []}
                value={form.values.modality}
                hints={MODALITY_HINTS}
                error={form.errors.modality}
                onChange={(value) => form.set('modality', value)}
              />
              {form.values.attendee === 'minor' && (
                <Alert tone="info">
                  Para atender a una persona menor de edad necesitaremos la autorización de su representante legal. Te lo
                  explicamos en el primer contacto.
                </Alert>
              )}
            </div>
          )}

          {step === 1 && (
            <div className="request__step" key="step-1">
              <fieldset className="request__group">
                <legend className="field__label">
                  Franjas que te quedan mejor <span className="field__optional">(puedes elegir varias)</span>
                </legend>
                <div className="chip-set">
                  {(site?.form.times ?? []).map((time) => (
                    <label key={time.value} className="chip">
                      <input
                        type="checkbox"
                        checked={form.values.preferred_times.includes(time.value)}
                        onChange={() => toggleTime(time.value)}
                      />
                      {form.values.preferred_times.includes(time.value) && <Icon name="check" size={14} />}
                      {time.label}
                    </label>
                  ))}
                </div>
              </fieldset>

              {site && site.professionals.length > 0 && (
                <SelectField
                  label="¿Prefieres a alguien del equipo?"
                  optional
                  placeholder="Sin preferencia"
                  options={site.professionals.map((person) => ({ value: String(person.id), label: person.full_name }))}
                  {...form.bind('preferred_professional_id')}
                />
              )}

              <TextAreaField
                label="¿Algo que quieras que sepamos?"
                optional
                rows={4}
                maxLength={600}
                hint="Por ejemplo, días en los que no puedes. Evita incluir información de salud: la hablaremos en privado."
                {...form.bind('message')}
              />
            </div>
          )}

          {step === 2 && (
            <div className="request__step" key="step-2">
              <div className="form-grid">
                <TextField label="Nombre" autoComplete="name" {...form.bind('full_name')} wrapperClassName="span-full" />
                <TextField label="Correo electrónico" type="email" autoComplete="email" inputMode="email" {...form.bind('email')} />
                <TextField
                  label="Teléfono"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  optional={form.values.contact_preference === 'email'}
                  {...form.bind('phone')}
                />
              </div>

              <ChoiceGroup
                name="contact_preference"
                legend="¿Cómo prefieres que te contactemos?"
                options={(site?.form.contactPreferences ?? []).filter(
                  (option) => option.value !== 'whatsapp' || Boolean(site?.clinic.whatsapp_number),
                )}
                value={form.values.contact_preference}
                hints={{}}
                error={form.errors.contact_preference}
                onChange={(value) => form.set('contact_preference', value)}
              />

              <div className={form.errors.privacy_accepted ? 'field has-error' : 'field'}>
                <label className="check">
                  <input
                    id="privacy_accepted"
                    type="checkbox"
                    checked={form.values.privacy_accepted}
                    onChange={(event) => form.set('privacy_accepted', event.target.checked)}
                    aria-describedby={form.errors.privacy_accepted ? 'privacy-error' : undefined}
                  />
                  <span>
                    Autorizo usar estos datos solo para responder a mi solicitud, según la{' '}
                    <Link to="/privacidad" target="_blank">
                      política de privacidad
                    </Link>
                    .
                  </span>
                </label>
                {form.errors.privacy_accepted && (
                  <span className="field__error" id="privacy-error" role="alert">
                    {form.errors.privacy_accepted}
                  </span>
                )}
              </div>
            </div>
          )}

          <div className="request__nav">
            {step > 0 ? (
              <Button variant="quiet" icon="arrowLeft" onClick={() => setStep((current) => current - 1)}>
                Atrás
              </Button>
            ) : (
              <span />
            )}
            {step < 2 ? (
              <Button variant="primary" iconRight="arrowRight" onClick={next} disabled={isPending}>
                Continuar
              </Button>
            ) : (
              <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Enviando solicitud">
                Enviar solicitud
              </Button>
            )}
          </div>
        </form>

        <p className="request__footnote">
          <Icon name="lock" size={14} />
          Solo el equipo del consultorio puede ver tu solicitud.
        </p>
      </div>
    </section>
  );
}
