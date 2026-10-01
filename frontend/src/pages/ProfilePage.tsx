import { Button } from '@/components/ui/Button';
import { PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, PasswordField, TextField } from '@/components/ui/Field';
import { useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { put } from '@/lib/api';
import { useSession } from '@/session/SessionProvider';

/** Perfil propio. Cambiar la contrasena exige confirmar la actual. */
export default function ProfilePage() {
  const { user, refresh } = useSession();
  const toast = useToast();
  useDocumentTitle('Mi perfil');
  const isStaff = user?.role !== 'patient';

  const form = useForm({
    initial: {
      full_name: user?.full_name ?? '',
      email: user?.email ?? '',
      phone: user?.phone ?? '',
      license_number: user?.license_number ?? '',
      specialty: user?.specialty ?? '',
      current_password: '',
      password: '',
      password_confirmation: '',
    },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.full_name.trim()) errors.full_name = 'Escribe tu nombre.';
      if (values.password) {
        if (!values.current_password) errors.current_password = 'Escribe tu contraseña actual.';
        if (values.password.length < 10) errors.password = 'Usa al menos 10 caracteres.';
        if (values.password !== values.password_confirmation) errors.password_confirmation = 'Las contraseñas no coinciden.';
      }
      return errors;
    },
    onSubmit: async (values) => {
      const result = await put('/api/profile', values);
      toast.success(result.message ?? 'Datos guardados.');
      form.setValues({ ...values, current_password: '', password: '', password_confirmation: '' });
      await refresh();
    },
  });

  return (
    <>
      <PageHeader title="Mi perfil" subtitle={`Usuario: ${user?.username ?? ''}`} />
      <form onSubmit={form.handleSubmit} noValidate className="container--narrow">
        <Panel>
          <FormAlert message={form.formError} />
          <section className="form-section">
            <h2 className="form-section__title">Tus datos</h2>
            <div className="form-grid">
              <TextField label="Nombre completo" autoComplete="name" {...form.bind('full_name')} />
              <TextField label="Correo" type="email" autoComplete="email" {...form.bind('email')} />
              <TextField label="Teléfono" type="tel" optional autoComplete="tel" {...form.bind('phone')} />
              {isStaff && <TextField label="Registro profesional" optional {...form.bind('license_number')} />}
              {isStaff && <TextField label="Especialidad" optional {...form.bind('specialty')} />}
            </div>
          </section>

          <section className="form-section">
            <h2 className="form-section__title">Cambiar contraseña</h2>
            <p className="form-section__intro">Déjalo en blanco si no quieres cambiarla.</p>
            <div className="form-grid">
              <PasswordField label="Contraseña actual" autoComplete="current-password" wrapperClassName="span-full" {...form.bind('current_password')} />
              <PasswordField label="Nueva contraseña" autoComplete="new-password" hint="Mínimo 10 caracteres." {...form.bind('password')} />
              <PasswordField label="Repite la nueva contraseña" autoComplete="new-password" {...form.bind('password_confirmation')} />
            </div>
          </section>

          <div className="form-actions">
            <Button type="submit" variant="primary" loading={form.submitting}>
              Guardar cambios
            </Button>
          </div>
        </Panel>
      </form>
    </>
  );
}
