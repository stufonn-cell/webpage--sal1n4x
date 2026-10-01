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
  { to: '/portal', label: 'Inicio', icon: 'home', end: true },
  { to: '/portal/citas', label: 'Mis citas', icon: 'calendar' },
  { to: '/portal/cuestionarios', label: 'Cuestionarios', icon: 'clipboard' },
  { to: '/portal/documentos', label: 'Documentos', icon: 'file' },
];

function ConsentPage() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  useDocumentTitle('Consentimiento');

  return (
    <>
      <PageHeader back={{ to: '/portal', label: 'Volver al inicio' }} title="Consentimiento informado" />
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
    toast.info('Cerraste sesión. Cuídate mucho.');
    navigate('/ingresar', { replace: true });
  };

  return (
    <div className="portal">
      <header className="portal-nav">
        <div className="container portal-nav__inner">
          <Logo name={clinicName} to="/portal" tagline="Portal del paciente" />
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
            <NavLink to="/portal/perfil" className="btn btn--quiet btn--icon" aria-label="Mi perfil" title={user?.full_name}>
              <Icon name="user" size={18} />
            </NavLink>
            <button className="btn btn--quiet btn--icon" type="button" onClick={onLogout} aria-label="Cerrar sesión" title="Cerrar sesión">
              <Icon name="logout" size={18} />
            </button>
          </div>
        </div>
      </header>

      <main id="contenido" className="container portal__main" tabIndex={-1}>
        <Routes>
          <Route index element={<PortalHome />} />
          <Route path="citas" element={<AppointmentsPage />} />
          <Route path="cuestionarios" element={<QuestionnairesPage />} />
          <Route path="cuestionarios/:id" element={<QuestionnairePage />} />
          <Route path="documentos" element={<DocumentsPage />} />
          <Route path="consentimientos/:id" element={<ConsentPage />} />
          <Route path="perfil" element={<ProfilePage />} />
          <Route
            path="*"
            element={<EmptyState icon="alert" title="Esta página no existe" action={<ButtonLink to="/portal" size="sm">Volver al inicio</ButtonLink>} />}
          />
        </Routes>
      </main>

      <footer className="container portal__footer">
        <p className="xsmall muted">
          Si estás en peligro o piensas en hacerte daño, no esperes a tu cita: llama a la línea de emergencias de tu país.
        </p>
        <AuthorCredit />
      </footer>
    </div>
  );
}
