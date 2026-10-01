import { Button } from '@/components/ui/Button';
import { PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, PasswordField, SelectField, TextField } from '@/components/ui/Field';
import { useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { put } from '@/lib/api';
import { useMeta } from '@/lib/meta';
import { useSession } from '@/session/SessionProvider';

/** The signed-in person's own profile. Changing the password requires the current one. */
export default function ProfilePage() {
  const { user, refresh } = useSession();
  const toast = useToast();
  useDocumentTitle('My profile');
  const isStaff = user?.role !== 'patient';
  // Catalogs are staff-only; the portal never requests them.
  const { data: meta } = useMeta({ enabled: isStaff });

  const form = useForm({
    initial: {
      full_name: user?.full_name ?? '',
      email: user?.email ?? '',
      phone: user?.phone ?? '',
      license_number: user?.license_number ?? '',
      specialty: user?.specialty ?? '',
      document_type: user?.document_type ?? 'CC',
      document_number: user?.document_number ?? '',
      current_password: '',
      password: '',
      password_confirmation: '',
    },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.full_name.trim()) errors.full_name = 'Enter your name.';
      if (values.password) {
        if (!values.current_password) errors.current_password = 'Enter your current password.';
        if (values.password.length < 10) errors.password = 'Use at least 10 characters.';
        if (values.password !== values.password_confirmation) errors.password_confirmation = "The passwords don't match.";
      }
      return errors;
    },
    onSubmit: async (values) => {
      const result = await put('/api/profile', values);
      toast.success(result.message ?? 'Details saved.');
      form.setValues({ ...values, current_password: '', password: '', password_confirmation: '' });
      await refresh();
    },
  });

  return (
    <>
      <PageHeader title="My profile" subtitle={`Username: ${user?.username ?? ''}`} />
      <form onSubmit={form.handleSubmit} noValidate className="container--narrow">
        <Panel>
          <FormAlert message={form.formError} />
          <section className="form-section">
            <h2 className="form-section__title">Your details</h2>
            <div className="form-grid">
              <TextField label="Full name" autoComplete="name" {...form.bind('full_name')} />
              <TextField label="Email" type="email" autoComplete="email" {...form.bind('email')} />
              <TextField label="Phone" type="tel" optional autoComplete="tel" {...form.bind('phone')} />
              {isStaff && <TextField label="Professional license" optional {...form.bind('license_number')} />}
              {isStaff && <TextField label="Specialty" optional {...form.bind('specialty')} />}
            </div>
          </section>

          {isStaff && (
            <section className="form-section">
              <h2 className="form-section__title">ID document</h2>
              <p className="form-section__intro">RIPS reports it for every consultation you attend.</p>
              <div className="form-grid">
                <SelectField label="Document type" options={meta?.rips.documentTypes ?? []} {...form.bind('document_type')} />
                <TextField label="Document number" optional inputMode="numeric" {...form.bind('document_number')} />
              </div>
            </section>
          )}

          <section className="form-section">
            <h2 className="form-section__title">Change password</h2>
            <p className="form-section__intro">Leave it blank if you don't want to change it.</p>
            <div className="form-grid">
              <PasswordField label="Current password" autoComplete="current-password" wrapperClassName="span-full" {...form.bind('current_password')} />
              <PasswordField label="New password" autoComplete="new-password" hint="At least 10 characters." {...form.bind('password')} />
              <PasswordField label="Repeat the new password" autoComplete="new-password" {...form.bind('password_confirmation')} />
            </div>
          </section>

          <div className="form-actions">
            <Button type="submit" variant="primary" loading={form.submitting}>
              Save changes
            </Button>
          </div>
        </Panel>
      </form>
    </>
  );
}
