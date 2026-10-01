import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { useActiveSection } from '@/hooks/useActiveSection';
import { useScrolled } from '@/hooks/useScrolled';
import { cssVars } from '@/lib/style';
import { homeFor, useSession } from '@/session/SessionProvider';
import { telLink, useSite, whatsappLink } from './useSite';
import './site.css';

const SECTIONS = [
  { id: 'services', label: 'Services' },
  { id: 'getting-started', label: 'Getting started' },
  { id: 'team', label: 'Team' },
  { id: 'faq', label: 'FAQ' },
  { id: 'contact', label: 'Contact' },
];

export default function SiteLayout() {
  const { data } = useSite();
  const { user } = useSession();
  const location = useLocation();
  const scrolled = useScrolled(12);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuButton = useRef<HTMLButtonElement>(null);
  const menu = useRef<HTMLDivElement>(null);

  const onHome = location.pathname === '/';
  const sections = SECTIONS.filter((section) => section.id !== 'team' || (data?.professionals.length ?? 0) > 0);
  const active = useActiveSection(onHome ? sections.map((section) => section.id) : []);
  const clinic = data?.clinic;
  const name = clinic?.clinic_name || 'PsiClinic';
  const tagline = clinic?.clinic_tagline || 'Psychological care center';
  const crisisLine = clinic?.crisis_line || '123';
  const account = user ? { to: homeFor(user), label: 'My space' } : { to: '/login', label: 'Log in' };

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

  // The open menu fills the screen below the bar; where the bar ends depends on
  // whether the top strip has scrolled away, so measure it.
  useLayoutEffect(() => {
    const element = menu.current;
    if (menuOpen && element) element.style.setProperty('--menu-top', `${element.getBoundingClientRect().top}px`);
  }, [menuOpen]);

  const href = (id: string) => (onHome ? `#${id}` : `/#${id}`);

  return (
    <div className="site">
      <header className={['site-header', scrolled && 'is-scrolled', menuOpen && 'is-open'].filter(Boolean).join(' ')}>
        <div className="site-strip">
          <div className="container site-strip__inner">
            <p className="site-strip__help">
              Need help<span className="site-strip__help-long"> right now</span>?{' '}
              <a href={telLink(crisisLine)}>Call {crisisLine}</a>
            </p>

            {clinic && (clinic.clinic_phone || clinic.whatsapp_number) && (
              <ul className="site-strip__contact" aria-label="Talk to the practice">
                {clinic.clinic_phone && (
                  <li>
                    <a href={telLink(clinic.clinic_phone)}>
                      <Icon name="phone" size={14} />
                      {clinic.clinic_phone}
                    </a>
                  </li>
                )}
                {clinic.whatsapp_number && (
                  <li>
                    <a href={whatsappLink(clinic.whatsapp_number)} target="_blank" rel="noopener noreferrer">
                      <Icon name="message" size={14} />
                      WhatsApp
                    </a>
                  </li>
                )}
              </ul>
            )}

            <div className="site-strip__account">
              <Link className="site-strip__login" to={account.to}>
                {account.label}
              </Link>
              <ThemeToggle />
            </div>
          </div>
        </div>

        <div className="site-bar">
          <div className="container site-bar__inner">
            <Logo name={name} tagline={tagline} />

            <nav className="site-bar__links" aria-label="Sections">
              {sections.map((section) => (
                <a
                  key={section.id}
                  href={href(section.id)}
                  className={active === section.id ? 'site-bar__link is-active' : 'site-bar__link'}
                  aria-current={active === section.id ? 'true' : undefined}
                >
                  {section.label}
                </a>
              ))}
            </nav>

            <Link className="site-bar__cta" to="/request-appointment">
              Book an appointment
              <Icon name="arrowRight" size={16} />
            </Link>

            <button
              ref={menuButton}
              className="btn btn--quiet btn--icon site-bar__menu"
              type="button"
              aria-expanded={menuOpen}
              aria-controls="mobile-menu"
              aria-label={menuOpen ? 'Close menu' : 'Open menu'}
              onClick={() => setMenuOpen((open) => !open)}
            >
              <Icon name={menuOpen ? 'close' : 'menu'} size={22} />
            </button>
          </div>
        </div>

        <div id="mobile-menu" ref={menu} className="mobile-menu" hidden={!menuOpen}>
          <nav aria-label="Main menu" className="container">
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
              <ButtonLink to="/request-appointment" variant="primary" size="lg" block>
                Book an appointment
              </ButtonLink>
              <ButtonLink to={account.to} size="lg" block>
                {user ? 'Go to my space' : 'Log in to the portal'}
              </ButtonLink>
            </div>
            {clinic && (clinic.clinic_phone || clinic.whatsapp_number) && (
              <ul className="mobile-menu__contact">
                {clinic.clinic_phone && (
                  <li>
                    <a href={telLink(clinic.clinic_phone)}>
                      <Icon name="phone" size={16} />
                      {clinic.clinic_phone}
                    </a>
                  </li>
                )}
                {clinic.whatsapp_number && (
                  <li>
                    <a href={whatsappLink(clinic.whatsapp_number)} target="_blank" rel="noopener noreferrer">
                      <Icon name="message" size={16} />
                      WhatsApp
                    </a>
                  </li>
                )}
              </ul>
            )}
          </nav>
        </div>
      </header>

      <main id="main-content" tabIndex={-1}>
        <Outlet />
      </main>

      <aside className="crisis" aria-label="Immediate help">
        <div className="container crisis__inner">
          <Icon name="phone" size={18} />
          <p>
            <strong>Are you in danger or thinking about hurting yourself?</strong> Don’t wait for an appointment: call the
            emergency line <a href={telLink(crisisLine)}>{crisisLine}</a> now or go to the nearest emergency room.
          </p>
        </div>
      </aside>

      <footer className="site-footer">
        <div className="container site-footer__grid">
          <div className="site-footer__brand">
            <Logo name={name} tagline={tagline} />
          </div>

          <nav aria-label="Site">
            <p className="eyebrow">The practice</p>
            <ul className="site-footer__list">
              {sections.map((section) => (
                <li key={section.id}>
                  <a href={href(section.id)}>{section.label}</a>
                </li>
              ))}
            </ul>
          </nav>

          <div>
            <p className="eyebrow">Contact</p>
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
            <p className="eyebrow">Quick links</p>
            <ul className="site-footer__list">
              <li>
                <NavLink to="/request-appointment">Request an appointment</NavLink>
              </li>
              <li>
                <NavLink to="/login">Patient portal</NavLink>
              </li>
              <li>
                <NavLink to="/privacy">Privacy and data</NavLink>
              </li>
              <li>
                <NavLink to="/terms">Terms and conditions</NavLink>
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
