import { useQuery } from '@tanstack/react-query';
import { useEffect, useState, type ReactNode } from 'react';
import { Link, NavLink, useLocation, useNavigate } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Avatar } from '@/components/ui/Display';
import { Icon, type IconName } from '@/components/ui/Icon';
import { AuthorCredit, Logo } from '@/components/ui/Logo';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { useToast } from '@/components/ui/Feedback';
import { get } from '@/lib/api';
import { labelOf, useMeta } from '@/lib/meta';
import { useSession } from '@/session/SessionProvider';
import { CommandSearch } from './CommandSearch';

interface NavItem {
  to: string;
  label: string;
  icon: IconName;
  end?: boolean;
  badge?: number;
}

function useNewRequests(): number {
  const { data } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => get<{ metrics: { new_requests: number } }>('/api/dashboard'),
    staleTime: 60_000,
    select: (dashboard) => dashboard.metrics.new_requests,
  });
  return data ?? 0;
}

export function StaffLayout({ children }: { children: ReactNode }) {
  const { user, clinicName, logout } = useSession();
  const { data: meta } = useMeta();
  const toast = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const newRequests = useNewRequests();

  useEffect(() => setOpen(false), [location.pathname]);

  const groups: { title: string; items: NavItem[] }[] = [
    {
      title: 'Consulta',
      items: [
        { to: '/app', label: 'Inicio', icon: 'home', end: true },
        { to: '/app/solicitudes', label: 'Solicitudes', icon: 'inbox', badge: newRequests },
        { to: '/app/agenda', label: 'Agenda', icon: 'calendar' },
        { to: '/app/pacientes', label: 'Pacientes', icon: 'users' },
        { to: '/app/notas', label: 'Notas de sesión', icon: 'note' },
        { to: '/app/evaluaciones', label: 'Evaluaciones', icon: 'chart' },
      ],
    },
    {
      title: 'Gestión',
      items: [
        { to: '/app/consentimientos', label: 'Consentimientos', icon: 'clipboard' },
        { to: '/app/documentos', label: 'Documentos', icon: 'file' },
        { to: '/app/facturacion', label: 'Facturación', icon: 'receipt' },
      ],
    },
    {
      title: 'Sistema',
      items: [
        { to: '/app/ajustes', label: 'Configuración', icon: 'settings', end: true },
        { to: '/app/ajustes/usuarios', label: 'Usuarios', icon: 'user' },
        { to: '/app/ajustes/auditoria', label: 'Auditoría', icon: 'shield' },
      ],
    },
  ];

  const onLogout = async () => {
    await logout();
    toast.info('Cerraste sesión. Hasta pronto.');
    navigate('/ingresar', { replace: true });
  };

  return (
    <div className={open ? 'shell is-nav-open' : 'shell'}>
      <aside className="sidebar" id="navegacion" aria-label="Navegación principal">
        <div className="sidebar__brand">
          <Logo name={clinicName} to="/app" tagline="Historia clínica" />
        </div>

        <nav className="sidebar__nav">
          {groups.map((group) => (
            <div className="sidebar__group" key={group.title}>
              <p className="sidebar__title">{group.title}</p>
              {group.items.map((item) => (
                <NavLink key={item.to} to={item.to} end={item.end} className="sidebar__link">
                  <Icon name={item.icon} size={18} />
                  <span>{item.label}</span>
                  {item.badge ? (
                    <span className="sidebar__badge" aria-label={`${item.badge} nuevas`}>
                      {item.badge}
                    </span>
                  ) : null}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        <div className="sidebar__footer">
          <Link to="/app/perfil" className="sidebar__me">
            <Avatar name={user?.full_name ?? ''} size="sm" />
            <span className="sidebar__me-text">
              <span className="sidebar__me-name">{user?.full_name}</span>
              <span className="sidebar__me-role">{labelOf(meta?.roles, user?.role)}</span>
            </span>
          </Link>
          <AuthorCredit />
        </div>
      </aside>

      <button className="shell__scrim" type="button" aria-label="Cerrar menú" tabIndex={-1} onClick={() => setOpen(false)} />

      <div className="shell__main">
        <header className="topbar">
          <button
            className="btn btn--quiet btn--icon topbar__menu"
            type="button"
            aria-label={open ? 'Cerrar menú' : 'Abrir menú'}
            aria-expanded={open}
            aria-controls="navegacion"
            onClick={() => setOpen((value) => !value)}
          >
            <Icon name={open ? 'close' : 'menu'} size={20} />
          </button>

          <CommandSearch />

          <div className="topbar__actions">
            <ButtonLink to="/app/agenda/nueva" icon="calendar" size="sm" className="topbar__action">
              Agendar
            </ButtonLink>
            <ButtonLink to="/app/pacientes/nuevo" variant="primary" icon="plus" size="sm" className="topbar__action">
              Paciente
            </ButtonLink>
            <ThemeToggle />
            <button className="btn btn--quiet btn--icon" type="button" onClick={onLogout} aria-label="Cerrar sesión" title="Cerrar sesión">
              <Icon name="logout" size={18} />
            </button>
          </div>
        </header>

        <main className="content" id="contenido" tabIndex={-1}>
          {children}
        </main>
      </div>
    </div>
  );
}
