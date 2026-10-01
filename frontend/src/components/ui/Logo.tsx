import { Link } from 'react-router';

/*
 * The mark is a psi (Ψ), the letter psychology has used as its sign for a
 * century, drawn here as a serif capital that matches the Literata wordmark:
 * a heavier left arm and a fine right one (broad-pen stress), slanted head
 * serifs, a bracketed foot. Custom paths on a 48 unit grid, not a font glyph,
 * filled with currentColor so the theme decides the color. public/logo.svg
 * (favicon) uses the same two paths.
 */
const STEM =
  'M17.2 5.1 27 3.45C26.9 14 26.8 22 26.8 30c0 4 .1 6.6.3 8.3.2 2.1 1.5 2.8 4.1 3l2.4.2v2.1H14.4v-2.1l2.4-.2c2.6-.2 3.9-.9 4.1-3 .2-1.7.3-4.3.3-8.3 0-8-.1-16-.2-20.2-.1-2-1.2-2.7-3.8-2.8Z';
const CUP =
  'M3.8 7.95 15 6.05v1.9c-2.2.35-2.8 1.25-2.8 3.25V17c0 6.8 3.4 11.4 9 11.6h5.6c5.4-.2 10.6-4.6 10.6-11.6v-5.8c0-1.3-.5-1.6-2.6-1.6V7.73l8.6-1.46v1.9c-2 .18-2.6.98-2.6 2.78V17c0 9-6.8 14.6-14 14.8h-5.6C14 31.6 6.6 26 6.6 17v-5.4c0-1.2-.6-1.7-2.8-1.75Z';

export function LogoMark({ size = 32 }: { size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="currentColor"
      aria-hidden="true"
      focusable="false"
      className="logo-mark"
    >
      <path d={STEM} />
      <path d={CUP} />
    </svg>
  );
}

export function Logo({ name, to = '/', tagline }: { name: string; to?: string; tagline?: string }) {
  return (
    <Link to={to} className={tagline ? 'logo logo--tagline' : 'logo'} aria-label={`${name}, go to the home page`}>
      <LogoMark />
      <span className="logo__text">
        <span className="logo__name">{name}</span>
        {tagline && <span className="logo__tagline">{tagline}</span>}
      </span>
    </Link>
  );
}

/** The authorship credit is required by NOTICE. */
export function AuthorCredit() {
  return (
    <span className="author-credit">
      Made by Salinas ·{' '}
      <a href="https://github.com/stufonn-cell" target="_blank" rel="noopener noreferrer">
        stufonn-cell
      </a>
    </span>
  );
}
