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

const STEP_TITLES = ['Who is the appointment for?', 'When works best for you?', 'How can we reach you?'];

const ATTENDEE_HINTS: Record<string, string> = {
  self: 'You want to start a process for yourself.',
  minor: 'You are a parent or legal guardian.',
  other: 'You are asking on behalf of someone else.',
};

const MODALITY_HINTS: Record<string, string> = {
  in_person: 'At the office.',
  online: 'By video call or phone.',
  no_preference: 'We decide together.',
};

function validateStep(step: number, values: RequestValues): FieldErrors {
  const errors: FieldErrors = {};
  if (step === 0) {
    if (!values.attendee) errors.attendee = 'Choose an option.';
    if (!values.modality) errors.modality = 'Choose an option.';
  }
  if (step === 2) {
    if (!values.full_name.trim()) errors.full_name = 'Please enter your name.';
    if (!values.email.trim()) errors.email = 'Please enter your email.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) errors.email = 'Check your email; it looks incomplete.';
    if (['phone', 'whatsapp'].includes(values.contact_preference) && !values.phone.trim()) {
      errors.phone = 'Leave us a number so we can call or message you.';
    }
    if (!values.privacy_accepted) errors.privacy_accepted = 'We need your permission to contact you.';
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

  const service = SERVICES.find((item) => item.id === params.get('service'));
  useDocumentTitle('Request an appointment', site?.clinic.clinic_name);
  useMetaDescription('Request a first appointment for psychological care, in person or online. We will contact you to agree on a time.');

  const form = useForm<RequestValues>({
    initial: {
      attendee: service?.id === 'children' ? 'minor' : '',
      modality: service?.id === 'online' ? 'online' : '',
      preferred_times: [],
      preferred_professional_id: '',
      message: service ? `I am interested in: ${service.title}.` : '',
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

  // If the API returns errors for an earlier step, go back to that step.
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
              Thank you{sentName ? `, ${sentName}` : ''}. We received your request.
            </h1>
            <p className="request__lead">
              Asking for help is not always easy, and you have already taken the first step. Someone from our team will review
              your request and get in touch with you, the way you chose, to agree on a day and time.
            </p>
            <Alert tone="info" title="If you need help right now">
              This request is not an emergency appointment. If you are in danger, call the emergency line{' '}
              <a href={telLink(crisisLine)}>{crisisLine}</a>.
            </Alert>
            <div className="cluster">
              <ButtonLink to="/" variant="primary">
                Back to home
              </ButtonLink>
              <ButtonLink to="/#faq" variant="quiet">
                Read the FAQ
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
          <p className="eyebrow">Appointment request</p>
          <h1 className="request__title serif" id="request-title">
            Tell us a little and we will get in touch
          </h1>
          <p className="request__lead">
            It is three short steps. You do not need to explain your reasons for reaching out here: we will talk about that calmly.
          </p>
        </header>

        <div className="request__progress" aria-live="polite">
          <div className="split">
            <span className="small">
              Step {step + 1} of 3 · <strong>{STEP_TITLES[step]}</strong>
            </span>
          </div>
          <Progress value={step + 1} max={3} label="Request progress" />
        </div>

        <form
          className="request__card"
          noValidate
          onSubmit={(event) => {
            // Pressing Enter in the first steps moves forward instead of submitting.
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

          {/* Honeypot field for bots: hidden from people and from screen readers. */}
          <div className="visually-hidden" aria-hidden="true">
            <label htmlFor="website">Do not fill in this field</label>
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
                legend="The appointment is…"
                options={site?.form.attendees ?? []}
                value={form.values.attendee}
                hints={ATTENDEE_HINTS}
                error={form.errors.attendee}
                onChange={(value) => form.set('attendee', value)}
              />
              <ChoiceGroup
                name="modality"
                legend="How would you prefer to be seen?"
                options={site?.form.modalities ?? []}
                value={form.values.modality}
                hints={MODALITY_HINTS}
                error={form.errors.modality}
                onChange={(value) => form.set('modality', value)}
              />
              {form.values.attendee === 'minor' && (
                <Alert tone="info">
                  To see a minor, we will need the consent of their legal guardian. We will explain this when we first get in
                  touch.
                </Alert>
              )}
            </div>
          )}

          {step === 1 && (
            <div className="request__step" key="step-1">
              <fieldset className="request__group">
                <legend className="field__label">
                  Times that work best for you <span className="field__optional">(you can choose more than one)</span>
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
                  label="Would you prefer someone from the team?"
                  optional
                  placeholder="No preference"
                  options={site.professionals.map((person) => ({ value: String(person.id), label: person.full_name }))}
                  {...form.bind('preferred_professional_id')}
                />
              )}

              <TextAreaField
                label="Anything you would like us to know?"
                optional
                rows={4}
                maxLength={600}
                hint="For example, days when you are not available. Please avoid health details: we will talk about them in private."
                {...form.bind('message')}
              />
            </div>
          )}

          {step === 2 && (
            <div className="request__step" key="step-2">
              <div className="form-grid">
                <TextField label="Name" autoComplete="name" {...form.bind('full_name')} wrapperClassName="span-full" />
                <TextField label="Email" type="email" autoComplete="email" inputMode="email" {...form.bind('email')} />
                <TextField
                  label="Phone"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  optional={form.values.contact_preference === 'email'}
                  {...form.bind('phone')}
                />
              </div>

              <ChoiceGroup
                name="contact_preference"
                legend="How would you like us to contact you?"
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
                    I agree that this information will be used only to respond to my request, as described in the{' '}
                    <Link to="/privacy" target="_blank">
                      privacy policy
                    </Link>
                    , and I have read the{' '}
                    <Link to="/terms" target="_blank">
                      terms and conditions
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
                Back
              </Button>
            ) : (
              <span />
            )}
            {step < 2 ? (
              <Button variant="primary" iconRight="arrowRight" onClick={next} disabled={isPending}>
                Continue
              </Button>
            ) : (
              <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Sending request">
                Send request
              </Button>
            )}
          </div>
        </form>

        <p className="request__footnote">
          <Icon name="lock" size={14} />
          Only the practice team can see your request.
        </p>
      </div>
    </section>
  );
}
