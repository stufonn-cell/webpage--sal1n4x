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
  useDocumentTitle('Log in', clinicName);

  const form = useForm({
    initial: { identifier: '', password: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.identifier.trim()) errors.identifier = 'Please enter your username or email.';
      if (!values.password) errors.password = 'Please enter your password.';
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

      <main className="solo__main" id="main-content">
        <div className="login enter">
          <p className="eyebrow">{greeting()}</p>
          <h1 className="login__title">Log in to your space</h1>
          <p className="login__lead">
            For the clinical team and for patients with portal access. If you do not have an account yet, your
            professional can create one for you.
          </p>

          <form className="login__form" onSubmit={form.handleSubmit} noValidate>
            {state.expired && !form.formError && (
              <Alert tone="info">
                Log in to continue. For your security, sessions close after a period of inactivity.
              </Alert>
            )}
            <FormAlert message={form.formError} />

            <TextField
              label="Username or email"
              autoComplete="username"
              autoCapitalize="none"
              spellCheck={false}
              autoFocus
              {...form.bind('identifier')}
            />
            <PasswordField label="Password" autoComplete="current-password" {...form.bind('password')} />

            <Button type="submit" variant="primary" size="lg" block loading={form.submitting} loadingLabel="Checking">
              Log in
            </Button>
          </form>

          <p className="login__note">
            <Icon name="lock" size={15} />
            <span>
              Every login is recorded. If you forgot your password, ask the practice to
              reset it.
            </span>
          </p>

          {demoAccounts.length > 0 && (
            <section className="demo-accounts" aria-labelledby="demo-title">
              <p className="eyebrow" id="demo-title">
                Demo accounts · local only
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
          Back to the site
        </ButtonLink>
        <AuthorCredit />
      </footer>
    </div>
  );
}
