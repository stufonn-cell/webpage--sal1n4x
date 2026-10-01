import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { useActiveSection } from '@/hooks/useActiveSection';
import { useScrolled } from '@/hooks/useScrolled';
import { cssVars } from '@/lib/style';
import { homeFor, useSession } from '@/session/SessionProvider';
import { telLink, useSite } from './useSite';
import './site.css';

const SECTIONS = [
  { id: 'servicios', label: 'Servicios' },
  { id: 'proceso', label: 'Cómo empezar' },
  { id: 'equipo', label: 'Equipo' },
  { id: 'preguntas', label: 'Preguntas' },
  { id: 'contacto', label: 'Contacto' },
];

export default function SiteLayout() {
  const { data } = useSite();
  const { user } = useSession();
  const location = useLocation();
  const scrolled = useScrolled(12);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButton = useRef<HTMLButtonElement>(null);

  const onHome = location.pathname === '/';
  const sections = SECTIONS.filter((section) => section.id !== 'equipo' || (data?.professionals.length ?? 0) > 0);
  const active = useActiveSection(onHome ? sections.map((section) => section.id) : []);
  const clinic = data?.clinic;
  const name = clinic?.clinic_name || 'PsiClinic';

  useEffect(() => setMenuOpen(false), [location]);

  useEffect(() => {
    if (!menuOpen) return;
    document.body.classList.add('no-scroll');
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        menuButton.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.classList.remove('no-scroll');
      window.removeEventListener('keydown', onKey);
    };
  }, [menuOpen]);

  const href = (id: string) => (onHome ? `#${id}` : `/#${id}`);

  return (
    <div className="site">
      <header className={['site-nav', scrolled && 'is-scrolled', menuOpen && 'is-open'].filter(Boolean).join(' ')}>
        <div className="container site-nav__inner">
          <Logo name={name} />

          <nav className="site-nav__links" aria-label="Secciones">
            {sections.map((section) => (
              <a
                key={section.id}
                href={href(section.id)}
                className={active === section.id ? 'site-nav__link is-active' : 'site-nav__link'}
                aria-current={active === section.id ? 'true' : undefined}
              >
                {section.label}
              </a>
            ))}
          </nav>

          <div className="site-nav__actions">
            <ThemeToggle />
            <Link className="site-nav__login" to={user ? homeFor(user) : '/ingresar'}>
              {user ? 'Mi espacio' : 'Ingresar'}
            </Link>
            <ButtonLink to="/solicitar-cita" variant="primary" className="site-nav__cta">
              Pedir una cita
            </ButtonLink>
            <button
              ref={menuButton}
              className="btn btn--quiet btn--icon site-nav__menu"
              type="button"
              aria-expanded={menuOpen}
              aria-controls="menu-movil"
              aria-label={menuOpen ? 'Cerrar menú' : 'Abrir menú'}
              onClick={() => setMenuOpen((open) => !open)}
            >
              <Icon name={menuOpen ? 'close' : 'menu'} size={22} />
            </button>
          </div>
        </div>

        <div id="menu-movil" className="mobile-menu" hidden={!menuOpen}>
          <nav aria-label="Menú principal" className="container">
            <ul className="mobile-menu__list">
              {sections.map((section, index) => (
                <li key={section.id} style={cssVars({ '--i': index })}>
                  <a href={href(section.id)} onClick={() => setMenuOpen(false)}>
                    {section.label}
                    <Icon name="arrowRight" size={18} />
                  </a>
                </li>
              ))}
            </ul>
            <div className="mobile-menu__actions">
              <ButtonLink to="/solicitar-cita" variant="primary" size="lg" block>
                Pedir una cita
              </ButtonLink>
              <ButtonLink to={user ? homeFor(user) : '/ingresar'} size="lg" block>
                {user ? 'Ir a mi espacio' : 'Ingresar al portal'}
              </ButtonLink>
            </div>
          </nav>
        </div>
      </header>

      <main id="contenido" tabIndex={-1}>
        <Outlet />
      </main>

      <aside className="crisis" aria-label="Ayuda inmediata">
        <div className="container crisis__inner">
          <Icon name="phone" size={18} />
          <p>
            <strong>¿Estás en peligro o piensas en hacerte daño?</strong> No esperes a una cita: llama ahora a la línea de
            emergencias <a href={telLink(clinic?.crisis_line || '123')}>{clinic?.crisis_line || '123'}</a> o acude al
            servicio de urgencias más cercano.
          </p>
        </div>
      </aside>

      <footer className="site-footer">
        <div className="container site-footer__grid">
          <div className="site-footer__brand">
            <Logo name={name} />
            <p className="soft small">{clinic?.clinic_tagline || 'Centro de atención psicológica'}</p>
          </div>

          <nav aria-label="Sitio">
            <p className="eyebrow">El consultorio</p>
            <ul className="site-footer__list">
              {sections.map((section) => (
                <li key={section.id}>
                  <a href={href(section.id)}>{section.label}</a>
                </li>
              ))}
            </ul>
          </nav>

          <div>
            <p className="eyebrow">Contacto</p>
            <ul className="site-footer__list">
              {clinic?.clinic_phone && (
                <li>
                  <a href={telLink(clinic.clinic_phone)}>{clinic.clinic_phone}</a>
                </li>
              )}
              {clinic?.clinic_email && (
                <li>
                  <a href={`mailto:${clinic.clinic_email}`}>{clinic.clinic_email}</a>
                </li>
              )}
              {clinic?.clinic_address && <li className="soft">{clinic.clinic_address}</li>}
            </ul>
          </div>

          <div>
            <p className="eyebrow">Accesos</p>
            <ul className="site-footer__list">
              <li>
                <NavLink to="/solicitar-cita">Solicitar una cita</NavLink>
              </li>
              <li>
                <NavLink to="/ingresar">Portal del paciente</NavLink>
              </li>
              <li>
                <NavLink to="/privacidad">Privacidad y datos</NavLink>
              </li>
            </ul>
          </div>
        </div>

        <div className="container site-footer__legal">
          <span>
            © {new Date().getFullYear()} {name}
          </span>
          <AuthorCredit />
        </div>
      </footer>
    </div>
  );
}
