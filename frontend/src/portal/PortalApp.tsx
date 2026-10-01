import { NavLink, Route, Routes, useNavigate, useParams } from 'react-router';
import { ConsentDocument } from '@/components/forms/ConsentDocument';
import { ButtonLink } from '@/components/ui/Button';
import { EmptyState, PageHeader } from '@/components/ui/Display';
import { useToast } from '@/components/ui/Feedback';
import { Icon, type IconName } from '@/components/ui/Icon';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import ProfilePage from '@/pages/ProfilePage';
import { useSession } from '@/session/SessionProvider';
import { AppointmentsPage, DocumentsPage } from './pages/ListPages';
import PortalHome from './pages/PortalHome';
import { QuestionnairePage, QuestionnairesPage } from './pages/Questionnaires';
import './portal.css';

const LINKS: { to: string; label: string; icon: IconName; end?: boolean }[] = [
  { to: '/portal', label: 'Home', icon: 'home', end: true },
  { to: '/portal/appointments', label: 'My appointments', icon: 'calendar' },
  { to: '/portal/questionnaires', label: 'Questionnaires', icon: 'clipboard' },
  { to: '/portal/documents', label: 'Documents', icon: 'file' },
];

function ConsentPage() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  useDocumentTitle('Consent');

  return (
    <>
      <PageHeader back={{ to: '/portal', label: 'Back to home' }} title="Informed consent" />
      <ConsentDocument id={id} audience="patient" onSigned={() => navigate('/portal')} />
    </>
  );
}

export default function PortalApp() {
  const { user, clinicName, logout } = useSession();
  const toast = useToast();
  const navigate = useNavigate();

  const onLogout = async () => {
    await logout();
    toast.info('You have logged out. Take good care of yourself.');
    navigate('/login', { replace: true });
  };

  return (
    <div className="portal">
      <header className="portal-nav">
        <div className="container portal-nav__inner">
          <Logo name={clinicName} to="/portal" tagline="Patient portal" />
          <nav className="portal-nav__links" aria-label="Portal">
            {LINKS.map((link) => (
              <NavLink key={link.to} to={link.to} end={link.end} className="portal-nav__link">
                <Icon name={link.icon} size={18} />
                <span>{link.label}</span>
              </NavLink>
            ))}
          </nav>
          <div className="portal-nav__actions">
            <ThemeToggle />
            <NavLink to="/portal/profile" className="btn btn--quiet btn--icon" aria-label="My profile" title={user?.full_name}>
              <Icon name="user" size={18} />
            </NavLink>
            <button className="btn btn--quiet btn--icon" type="button" onClick={onLogout} aria-label="Log out" title="Log out">
              <Icon name="logout" size={18} />
            </button>
          </div>
        </div>
      </header>

      <main id="main-content" className="container portal__main" tabIndex={-1}>
        <Routes>
          <Route index element={<PortalHome />} />
          <Route path="appointments" element={<AppointmentsPage />} />
          <Route path="questionnaires" element={<QuestionnairesPage />} />
          <Route path="questionnaires/:id" element={<QuestionnairePage />} />
          <Route path="documents" element={<DocumentsPage />} />
          <Route path="consents/:id" element={<ConsentPage />} />
          <Route path="profile" element={<ProfilePage />} />
          <Route
            path="*"
            element={<EmptyState icon="alert" title="This page does not exist" action={<ButtonLink to="/portal" size="sm">Back to home</ButtonLink>} />}
          />
        </Routes>
      </main>

      <footer className="container portal__footer">
        <p className="xsmall muted">
          If you are in danger or thinking about hurting yourself, don’t wait for your appointment: call your country’s emergency line.
        </p>
        <AuthorCredit />
      </footer>
    </div>
  );
}
