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
      <main className="solo__main" id="main-content">
        <div className="message-page enter">
          <p className="message-page__code">{code}</p>
          <h1>{title}</h1>
          <p>{text}</p>
          <div className="cluster cluster--center">
            <ButtonLink to="/" variant={user ? 'default' : 'primary'}>
              Go to home
            </ButtonLink>
            {user && (
              <ButtonLink to={homeFor(user)} variant="primary">
                Back to my space
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
  useDocumentTitle('Page not found');
  return (
    <MessagePage
      code="404"
      title="This page does not exist"
      text="The link may have changed or it may have a typo. You can find what you are looking for from the home page."
    />
  );
}
