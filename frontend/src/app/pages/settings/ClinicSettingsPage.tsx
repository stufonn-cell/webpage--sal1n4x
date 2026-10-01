import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { useLocation } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Alert, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, put } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import { useSession } from '@/session/SessionProvider';
import { useInvalidate } from '../../useAction';

type Settings = Record<string, string>;

function SettingsForm({ settings, readOnly, isAdmin }: { settings: Settings; readOnly: boolean; isAdmin: boolean }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const form = useForm<Settings>({
    initial: settings,
    onSubmit: async (values) => {
      const result = await put('/api/settings', values);
      toast.success(result.message ?? 'Settings saved.');
      await invalidate(['settings'], ['meta'], ['public-site'], ['session'], ['rips']);
    },
  });

  const field = (name: string) => ({ ...form.bind(name), disabled: readOnly });

  return (
    <form onSubmit={form.handleSubmit} noValidate>
      <Panel>
        <FormAlert message={form.formError} />
        {readOnly && <Alert tone="info">Only an administrator account can change these settings.</Alert>}

        <section className="form-section">
          <h2 className="form-section__title">The clinic</h2>
          <p className="form-section__intro">These details appear on the public site, on invoices and on printed notes.</p>
          <div className="form-grid">
            <TextField label="Name" {...field('clinic_name')} />
            <TextField label="Tagline" optional {...field('clinic_tagline')} />
            <TextAreaField
              label="Introduction on the site"
              optional
              rows={3}
              wrapperClassName="span-full"
              hint="Two or three sentences about how you support people. It appears on the home page."
              maxLength={800}
              {...field('clinic_about')}
            />
            <TextField label="Contact email" type="email" {...field('clinic_email')} />
            <TextField label="Phone" type="tel" optional {...field('clinic_phone')} />
            <TextField label="WhatsApp" optional hint="Digits only, with the country code. E.g. 573001234567. Leave empty to hide it." {...field('whatsapp_number')} />
            <TextField label="Address" optional {...field('clinic_address')} />
            <TextField label="Emergency line" hint="Shown in the immediate help notice on the site." {...field('crisis_line')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Documents</h2>
          <p className="form-section__intro">
            The app is in English, but consents, printed notes, invoices and assessment reports can be produced in English or Spanish.
            This is the language they start in; you can change it for each document.
          </p>
          <div className="form-grid">
            <SelectField label="Default document language" options={meta?.documentLanguages ?? []} {...field('document_language')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Schedule and billing</h2>
          <div className="form-grid">
            <TextField label="Session length (minutes)" type="number" min={15} {...field('session_duration')} />
            <TextField label="Workday start" type="time" {...field('working_hours_start')} />
            <TextField label="Workday end" type="time" {...field('working_hours_end')} />
            <TextField label="Default fee" type="number" min={0} hint="Not published on the site." {...field('default_fee')} />
            <TextField label="Currency" maxLength={3} {...field('currency')} />
            <TextField label="Suggested hours to sign a note" type="number" min={1} {...field('note_lock_hours')} />
          </div>
        </section>

        {isAdmin && (
          <section className="form-section" id="rips">
            <h2 className="form-section__title">RIPS</h2>
            <p className="form-section__intro">
              Details for the RIPS reports sent to Colombia's Ministry of Health (Resolution 2275 of 2023). They are never shown on the
              public site.
            </p>
            <div className="form-grid">
              <TextField
                label="NIT or ID of the reporting party"
                inputMode="numeric"
                hint="Digits only, without the verification digit. An independent professional uses their own ID number."
                {...field('rips_reporter_id')}
              />
              <TextField
                label="REPS provider code"
                inputMode="numeric"
                maxLength={12}
                hint="10 to 12 digits, as registered in the Special Registry of Health Providers."
                {...field('rips_provider_code')}
              />
              <TextField
                label="Enabled service code"
                inputMode="numeric"
                maxLength={4}
                hint="344 is Psychology (Resolution 3100 of 2019)."
                {...field('rips_service_code')}
              />
              <TextField
                label="First report number"
                type="number"
                min={1}
                hint="Reports are numbered from here. Raise it if you already reported with another system."
                {...field('rips_first_note_number')}
              />
              <SelectField label="Purpose of first consultations" options={meta?.rips.purposes ?? []} {...field('rips_purpose_first')} />
              <SelectField label="Purpose of follow-up consultations" options={meta?.rips.purposes ?? []} {...field('rips_purpose_follow_up')} />
              <SelectField label="Reason for care" hint="RIPS external cause. Most psychology consultations are general illness." options={meta?.rips.causes ?? []} {...field('rips_cause')} />
              <SelectField label="Environment" hint="For your reference: the validator decides where it reports." options={meta?.rips.environments ?? []} {...field('rips_environment')} />
              <TextField
                label="Validator Docker API address"
                optional
                type="url"
                placeholder="https://localhost:9443"
                hint="The Ministry's MUV Docker API running on your infrastructure. Leave it empty to only download the JSON."
                wrapperClassName="span-full"
                {...field('rips_validator_url')}
              />
              <label className="check span-full">
                <input
                  type="checkbox"
                  checked={form.values.rips_validator_verify_tls !== '0'}
                  onChange={(event) => form.set('rips_validator_verify_tls', event.target.checked ? '1' : '0')}
                  disabled={readOnly}
                />
                <span>Verify the validator's TLS certificate. Turn it off only for the self-signed certificate of a local Docker API.</span>
              </label>
            </div>
          </section>
        )}

        {!readOnly && (
          <div className="form-actions">
            <Button type="submit" variant="primary" loading={form.submitting}>
              Save settings
            </Button>
          </div>
        )}
      </Panel>
    </form>
  );
}

export default function ClinicSettingsPage() {
  const { user } = useSession();
  useDocumentTitle('Settings');
  const query = useQuery({ queryKey: ['settings'], queryFn: () => get<Settings>('/api/settings') });
  const isAdmin = user?.role === 'admin';
  const { hash } = useLocation();

  // Links such as /app/settings#rips land on their section once the form is rendered.
  useEffect(() => {
    if (hash && query.data) document.getElementById(hash.slice(1))?.scrollIntoView({ block: 'start' });
  }, [hash, query.data]);

  return (
    <>
      <PageHeader title="Clinic settings" />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        {query.data && <SettingsForm settings={query.data} readOnly={!isAdmin} isAdmin={isAdmin} />}
      </QueryState>
    </>
  );
}
