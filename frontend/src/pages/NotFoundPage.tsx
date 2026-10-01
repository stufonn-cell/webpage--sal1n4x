import { ButtonLink } from '@/components/ui/Button';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { homeFor, useSession } from '@/session/SessionProvider';

export function MessagePage({ code, title, text }: { code: string; title: string; text: string }) {
  const { clinicName, user } = useSession();

  return (
    <div className="solo">
      <header className="solo__top">
        <Logo name={clinicName} />
      </header>
      <main className="solo__main" id="contenido">
        <div className="message-page enter">
          <p className="message-page__code">{code}</p>
          <h1>{title}</h1>
          <p>{text}</p>
          <div className="cluster cluster--center">
            <ButtonLink to="/" variant={user ? 'default' : 'primary'}>
              Ir al inicio
            </ButtonLink>
            {user && (
              <ButtonLink to={homeFor(user)} variant="primary">
                Volver a mi espacio
              </ButtonLink>
            )}
          </div>
        </div>
      </main>
      <footer className="solo__bottom">
        <AuthorCredit />
      </footer>
    </div>
  );
}

export function NotFoundPage() {
  useDocumentTitle('Página no encontrada');
  return (
    <MessagePage
      code="404"
      title="Esta página no existe"
      text="Puede que el enlace haya cambiado o que se haya escrito con un error. Desde el inicio puedes encontrar lo que buscas."
    />
  );
}
