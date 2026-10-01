import { Link } from 'react-router';

export function LogoMark({ size = 32 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true" focusable="false" className="logo-mark">
      <rect width="48" height="48" rx="12" fill="var(--primary)" />
      <path
        d="M24 11c-6 0-10.8 4.3-10.8 9.8 0 3.5 1.9 6.6 4.9 8.4v4.1c0 1 1.1 1.6 2 1l3-2.2h.9c6 0 10.8-4.3 10.8-9.8S30 11 24 11Z"
        fill="var(--paper)"
      />
      <path
        d="M19.2 21.6c1.6 1.9 3.1 2.8 4.8 2.8s3.2-.9 4.8-2.8"
        fill="none"
        stroke="var(--primary)"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function Logo({ name, to = '/', tagline }: { name: string; to?: string; tagline?: string }) {
  return (
    <Link to={to} className="logo" aria-label={`${name}, ir al inicio`}>
      <LogoMark />
      <span className="logo__text">
        <span className="logo__name">{name}</span>
        {tagline && <span className="logo__tagline">{tagline}</span>}
      </span>
    </Link>
  );
}

/** El aviso de autoria es obligatorio segun NOTICE. */
export function AuthorCredit() {
  return (
    <span className="author-credit">
      Hecho por Salinas ·{' '}
      <a href="https://github.com/stufonn-cell" target="_blank" rel="noopener noreferrer">
        stufonn-cell
      </a>
    </span>
  );
}
