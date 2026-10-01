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
      title: 'Practice',
      items: [
        { to: '/app', label: 'Home', icon: 'home', end: true },
        { to: '/app/requests', label: 'Requests', icon: 'inbox', badge: newRequests },
        { to: '/app/schedule', label: 'Schedule', icon: 'calendar' },
        { to: '/app/patients', label: 'Patients', icon: 'users' },
        { to: '/app/notes', label: 'Session notes', icon: 'note' },
        { to: '/app/assessments', label: 'Assessments', icon: 'chart' },
      ],
    },
    {
      title: 'Management',
      items: [
        { to: '/app/consents', label: 'Consents', icon: 'clipboard' },
        { to: '/app/documents', label: 'Documents', icon: 'file' },
        { to: '/app/billing', label: 'Billing', icon: 'receipt' },
        ...(user?.role === 'admin' ? [{ to: '/app/rips', label: 'RIPS reports', icon: 'flag' as IconName }] : []),
      ],
    },
    {
      title: 'System',
      items: [
        { to: '/app/settings', label: 'Settings', icon: 'settings', end: true },
        { to: '/app/settings/users', label: 'Users', icon: 'user' },
        { to: '/app/settings/audit', label: 'Audit log', icon: 'shield' },
      ],
    },
  ];

  const onLogout = async () => {
    await logout();
    toast.info('You signed out. See you soon.');
    navigate('/login', { replace: true });
  };

  return (
    <div className={open ? 'shell is-nav-open' : 'shell'}>
      <aside className="sidebar" id="main-navigation" aria-label="Main navigation">
        <div className="sidebar__brand">
          <Logo name={clinicName} to="/app" tagline="Clinical records" />
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
                    <span className="sidebar__badge" aria-label={`${item.badge} new`}>
                      {item.badge}
                    </span>
                  ) : null}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        <div className="sidebar__footer">
          <Link to="/app/profile" className="sidebar__me">
            <Avatar name={user?.full_name ?? ''} size="sm" />
            <span className="sidebar__me-text">
              <span className="sidebar__me-name">{user?.full_name}</span>
              <span className="sidebar__me-role">{labelOf(meta?.roles, user?.role)}</span>
            </span>
          </Link>
          <AuthorCredit />
        </div>
      </aside>

      <button className="shell__scrim" type="button" aria-label="Close menu" tabIndex={-1} onClick={() => setOpen(false)} />

      <div className="shell__main">
        <header className="topbar">
          <button
            className="btn btn--quiet btn--icon topbar__menu"
            type="button"
            aria-label={open ? 'Close menu' : 'Open menu'}
            aria-expanded={open}
            aria-controls="main-navigation"
            onClick={() => setOpen((value) => !value)}
          >
            <Icon name={open ? 'close' : 'menu'} size={20} />
          </button>

          <CommandSearch />

          <div className="topbar__actions">
            <ButtonLink to="/app/schedule/new" icon="calendar" size="sm" className="topbar__action">
              Schedule
            </ButtonLink>
            <ButtonLink to="/app/patients/new" variant="primary" icon="plus" size="sm" className="topbar__action">
              Patient
            </ButtonLink>
            <ThemeToggle />
            <button className="btn btn--quiet btn--icon" type="button" onClick={onLogout} aria-label="Sign out" title="Sign out">
              <Icon name="logout" size={18} />
            </button>
          </div>
        </header>

        <main className="content" id="main-content" tabIndex={-1}>
          {children}
        </main>
      </div>
    </div>
  );
}
