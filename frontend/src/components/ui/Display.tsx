import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { initials } from '@/lib/format';
import { Icon, type IconName } from './Icon';

/** Piezas de presentacion pequenas y sin estado. */

export type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'primary';

export function Badge({ tone = 'neutral', plain, children }: { tone?: Tone; plain?: boolean; children: ReactNode }) {
  const className = ['badge', tone !== 'neutral' && `badge--${tone}`, plain && 'badge--plain'].filter(Boolean).join(' ');
  return <span className={className}>{children}</span>;
}

interface PanelProps {
  title?: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
  flush?: boolean;
  className?: string;
  children: ReactNode;
  as?: 'section' | 'div' | 'article';
  titleId?: string;
}

export function Panel({ title, subtitle, actions, flush, className, children, as: Tag = 'section', titleId }: PanelProps) {
  return (
    <Tag className={['panel', className].filter(Boolean).join(' ')} aria-labelledby={title ? titleId : undefined}>
      {(title || actions) && (
        <header className="panel__head">
          <div>
            {title && (
              <h2 className="panel__title" id={titleId}>
                {title}
              </h2>
            )}
            {subtitle && <p className="panel__subtitle">{subtitle}</p>}
          </div>
          {actions}
        </header>
      )}
      <div className={flush ? 'panel__body panel__body--flush' : 'panel__body'}>{children}</div>
    </Tag>
  );
}

interface PageHeaderProps {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
  back?: { to: string; label: string };
  eyebrow?: ReactNode;
}

export function PageHeader({ title, subtitle, actions, back, eyebrow }: PageHeaderProps) {
  return (
    <header className="page-header enter">
      <div>
        {back && (
          <Link className="page-header__back" to={back.to}>
            <Icon name="arrowLeft" size={15} />
            {back.label}
          </Link>
        )}
        {eyebrow && <p className="eyebrow">{eyebrow}</p>}
        <h1 className="page-header__title">{title}</h1>
        {subtitle && <p className="page-header__subtitle">{subtitle}</p>}
      </div>
      {actions && <div className="page-header__actions">{actions}</div>}
    </header>
  );
}

interface EmptyStateProps {
  icon?: IconName;
  title: string;
  text?: ReactNode;
  action?: ReactNode;
}

export function EmptyState({ icon = 'inbox', title, text, action }: EmptyStateProps) {
  return (
    <div className="empty">
      <span className="empty__icon">
        <Icon name={icon} size={20} />
      </span>
      <p className="empty__title">{title}</p>
      {text && <p className="empty__text">{text}</p>}
      {action}
    </div>
  );
}

export function Avatar({ name, size }: { name: string; size?: 'sm' | 'lg' }) {
  return (
    <span className={size ? `avatar avatar--${size}` : 'avatar'} aria-hidden="true">
      {initials(name) || '·'}
    </span>
  );
}

export function Alert({
  tone = 'info',
  title,
  children,
  icon,
}: {
  tone?: 'info' | 'success' | 'warning' | 'error';
  title?: string;
  children: ReactNode;
  icon?: IconName;
}) {
  const fallbackIcon: IconName = tone === 'success' ? 'checkCircle' : tone === 'info' ? 'info' : 'alert';
  return (
    <div className={`alert alert--${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon name={icon ?? fallbackIcon} size={17} />
      <div>
        {title && <p className="alert__title">{title}</p>}
        <div>{children}</div>
      </div>
    </div>
  );
}

export function Progress({ value, max, label }: { value: number; max: number; label: string }) {
  const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;
  return (
    <div
      className="progress"
      role="progressbar"
      aria-valuemin={0}
      aria-valuemax={max}
      aria-valuenow={value}
      aria-label={label}
    >
      <div className="progress__bar" style={{ width: `${percent}%` }} />
    </div>
  );
}

export function Facts({ items }: { items: [string, ReactNode][] }) {
  return (
    <dl className="facts">
      {items.map(([term, value]) => (
        <div key={term}>
          <dt>{term}</dt>
          <dd>{value === null || value === undefined || value === '' ? '—' : value}</dd>
        </div>
      ))}
    </dl>
  );
}
