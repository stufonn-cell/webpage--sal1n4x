import { Navigate, useLocation, useNavigate } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Display';
import { FormAlert, PasswordField, TextField } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { greeting } from '@/lib/format';
import type { User } from '@/lib/types';
import { homeFor, useSession } from '@/session/SessionProvider';

interface LocationState {
  from?: string;
  expired?: boolean;
}

function destinationFor(user: User, from?: string): string {
  if (from && from.startsWith(user.role === 'patient' ? '/portal' : '/app')) return from;
  return homeFor(user);
}

export default function LoginPage() {
  const { user, login, clinicName, demoAccounts, loading } = useSession();
  const navigate = useNavigate();
  const location = useLocation();
  const state = (location.state ?? {}) as LocationState;
  useDocumentTitle('Ingresar', clinicName);

  const form = useForm({
    initial: { identifier: '', password: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.identifier.trim()) errors.identifier = 'Escribe tu usuario o tu correo.';
      if (!values.password) errors.password = 'Escribe tu contraseña.';
      return errors;
    },
    onSubmit: async (values) => {
      const signedIn = await login(values.identifier.trim(), values.password);
      navigate(destinationFor(signedIn, state.from), { replace: true });
    },
  });

  if (!loading && user) return <Navigate to={homeFor(user)} replace />;

  return (
    <div className="solo">
      <header className="solo__top">
        <Logo name={clinicName} />
        <ThemeToggle />
      </header>

      <main className="solo__main" id="contenido">
        <div className="login enter">
          <p className="eyebrow">{greeting()}</p>
          <h1 className="login__title">Ingresa a tu espacio</h1>
          <p className="login__lead">
            Para el equipo clínico y para pacientes con acceso al portal. Si aún no tienes cuenta, tu profesional puede
            crearla por ti.
          </p>

          <form className="login__form" onSubmit={form.handleSubmit} noValidate>
            {state.expired && !form.formError && (
              <Alert tone="info">
                Ingresa para continuar. Por seguridad, las sesiones se cierran tras un tiempo sin actividad.
              </Alert>
            )}
            <FormAlert message={form.formError} />

            <TextField
              label="Usuario o correo"
              autoComplete="username"
              autoCapitalize="none"
              spellCheck={false}
              autoFocus
              {...form.bind('identifier')}
            />
            <PasswordField label="Contraseña" autoComplete="current-password" {...form.bind('password')} />

            <Button type="submit" variant="primary" size="lg" block loading={form.submitting} loadingLabel="Verificando">
              Ingresar
            </Button>
          </form>

          <p className="login__note">
            <Icon name="lock" size={15} />
            <span>
              Cada acceso queda registrado. Si olvidaste tu contraseña, pide al consultorio
              que la restablezca.
            </span>
          </p>

          {demoAccounts.length > 0 && (
            <section className="demo-accounts" aria-labelledby="demo-title">
              <p className="eyebrow" id="demo-title">
                Cuentas de demostración · solo en local
              </p>
              <ul className="demo-accounts__list">
                {demoAccounts.map((account) => (
                  <li key={account.identifier}>
                    <button
                      type="button"
                      onClick={() => form.setValues({ identifier: account.identifier, password: account.password })}
                    >
                      <span>{account.role}</span>
                      <code>{account.identifier}</code>
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </main>

      <footer className="solo__bottom">
        <ButtonLink to="/" variant="quiet" size="sm" icon="arrowLeft">
          Volver al sitio
        </ButtonLink>
        <AuthorCredit />
      </footer>
    </div>
  );
}
