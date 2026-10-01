import { useEffect, useRef, type ReactNode } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { useDocumentTitle, useMetaDescription } from '@/hooks/useDocumentTitle';
import { useReveal } from '@/hooks/useReveal';
import type { PublicSite } from '@/lib/types';
import { telLink } from './useSite';
import './legal.css';

/*
 * Shared layout for the legal pages (terms and privacy). The app is English
 * only, but the owner decided legal documents can also be read in Spanish.
 * The language lives in the URL (`?lang=es`) so a Spanish link can be shared;
 * without it the page is in English. This is deliberately not an i18n system:
 * each page simply writes both versions of its own text.
 */

export type LegalLang = 'en' | 'es';

const LANGUAGES: { code: LegalLang; label: string }[] = [
  { code: 'en', label: 'English' },
  { code: 'es', label: 'Español' },
];

/** Reads the document language from `?lang=`. English unless it says `es`. */
export function useLegalLang(): LegalLang {
  const [params] = useSearchParams();
  return params.get('lang') === 'es' ? 'es' : 'en';
}

/** Link to another legal page in the same language. */
export function legalHref(path: string, lang: LegalLang): string {
  return lang === 'es' ? `${path}?lang=es` : path;
}

export interface LegalSection {
  id: string;
  title: string;
  body: ReactNode;
}

export interface LegalContent {
  /** Browser tab title, without the clinic name. */
  documentTitle: string;
  description: string;
  eyebrow: string;
  title: string;
  lead: string;
  sections: LegalSection[];
}

/** Practice details the legal texts mention, taken from the public settings. */
export interface ClinicInfo {
  name: string;
  email: string;
  phone: string;
  address: string;
  crisisLine: string;
  sessionMinutes: number | null;
}

export function clinicInfo(site: PublicSite | undefined): ClinicInfo {
  const clinic = site?.clinic;
  const minutes = Number(clinic?.session_duration);
  return {
    name: clinic?.clinic_name?.trim() ?? '',
    email: clinic?.clinic_email?.trim() ?? '',
    phone: clinic?.clinic_phone?.trim() ?? '',
    address: clinic?.clinic_address?.trim() ?? '',
    crisisLine: clinic?.crisis_line?.trim() || '123',
    sessionMinutes: Number.isFinite(minutes) && minutes > 0 ? minutes : null,
  };
}

/** "October 1, 2026" / "1 de octubre de 2026", without time zone surprises. */
function formatLegalDate(isoDate: string, lang: LegalLang): string {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Intl.DateTimeFormat(lang === 'es' ? 'es-CO' : 'en-US', { dateStyle: 'long', timeZone: 'UTC' }).format(
    Date.UTC(year, month - 1, day),
  );
}

const NATIONAL_EMERGENCY_LINE = '123';

/** "call 106 or the national emergency line 123", with tappable numbers. */
export function CrisisLines({
  crisisLine,
  lang,
  sentenceStart = false,
}: {
  crisisLine: string;
  lang: LegalLang;
  /** Capitalizes the first word when the phrase starts a sentence. */
  sentenceStart?: boolean;
}) {
  const national = <a href={telLink(NATIONAL_EMERGENCY_LINE)}>{NATIONAL_EMERGENCY_LINE}</a>;
  const own = crisisLine.replace(/\D+/g, '') === NATIONAL_EMERGENCY_LINE ? null : <a href={telLink(crisisLine)}>{crisisLine}</a>;

  if (lang === 'es') {
    const verb = sentenceStart ? 'Llama' : 'llama';
    return own ? (
      <>
        {verb} a la línea {own} o a la línea nacional de emergencias {national}
      </>
    ) : (
      <>
        {verb} a la línea nacional de emergencias {national}
      </>
    );
  }

  const verb = sentenceStart ? 'Call' : 'call';
  return own ? (
    <>
      {verb} {own} or the national emergency line {national}
    </>
  ) : (
    <>
      {verb} the national emergency line {national}
    </>
  );
}

/** Email, phone and address from settings. Only shows what is filled in. */
export function ContactDetails({ info, lang }: { info: ClinicInfo; lang: LegalLang }) {
  const labels =
    lang === 'es'
      ? { email: 'Correo', phone: 'Teléfono', address: 'Dirección' }
      : { email: 'Email', phone: 'Phone', address: 'Address' };

  if (!info.email && !info.phone && !info.address) {
    return (
      <p>
        {lang === 'es' ? 'Encuentra nuestros canales de contacto en la ' : 'You can find how to reach us on our '}
        <Link to="/#contact">{lang === 'es' ? 'página de inicio' : 'home page'}</Link>.
      </p>
    );
  }

  return (
    <dl className="legal__contact">
      {info.email && (
        <div>
          <dt>{labels.email}</dt>
          <dd>
            <a href={`mailto:${info.email}`}>{info.email}</a>
          </dd>
        </div>
      )}
      {info.phone && (
        <div>
          <dt>{labels.phone}</dt>
          <dd>
            <a href={telLink(info.phone)}>{info.phone}</a>
          </dd>
        </div>
      )}
      {info.address && (
        <div>
          <dt>{labels.address}</dt>
          <dd>{info.address}</dd>
        </div>
      )}
    </dl>
  );
}

/**
 * "English / Español". Real links (shareable, work with a middle click) that
 * replace the history entry and keep the scroll position.
 */
function LanguageSwitch({ lang }: { lang: LegalLang }) {
  const { pathname } = useLocation();

  return (
    <nav className="legal-lang" aria-label={lang === 'es' ? 'Idioma del documento' : 'Document language'}>
      <ul className="legal-lang__list">
        {LANGUAGES.map((option) => (
          <li key={option.code}>
            <Link
              className="legal-lang__option"
              to={{ pathname, search: option.code === 'es' ? '?lang=es' : '' }}
              lang={option.code}
              hrefLang={option.code}
              aria-current={option.code === lang ? 'true' : undefined}
              replace
              preventScrollReset
            >
              {option.label}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}

export function LegalDocument({
  id,
  lang,
  content,
  clinicName,
  updated,
  toc = false,
}: {
  /** Prefix for the heading ids (`terms` -> `terms-title`). */
  id: string;
  lang: LegalLang;
  content: LegalContent;
  clinicName?: string;
  /** ISO date (YYYY-MM-DD) of the last update, shown under the lead. */
  updated?: string;
  /** Numbered headings plus an "On this page" list, for long documents. */
  toc?: boolean;
}) {
  const root = useRef<HTMLElement>(null);
  const { hash } = useLocation();
  const arrivalHash = useRef(hash);
  const es = lang === 'es';

  useDocumentTitle(content.documentTitle, clinicName || undefined);
  useMetaDescription(content.description);
  useReveal(root);

  // A shared link such as `/terms?lang=es#fees-and-payment` arrives before this
  // lazy page exists, so the browser cannot jump to the section by itself.
  useEffect(() => {
    const target = arrivalHash.current.slice(1);
    if (target) document.getElementById(decodeURIComponent(target))?.scrollIntoView();
  }, []);

  // useMetaDescription points the canonical link at the bare path (English).
  // The Spanish version is its own document, so it keeps its query.
  useEffect(() => {
    if (!es) return;
    const canonical = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (canonical) canonical.href = `${window.location.origin}${window.location.pathname}?lang=es`;
  }, [es, content.description]);

  return (
    <article className="legal" ref={root} lang={lang} aria-labelledby={`${id}-title`}>
      <div className="container container--narrow">
        <header className="legal__head enter">
          <div className="legal__bar">
            <p className="eyebrow">{content.eyebrow}</p>
            <LanguageSwitch lang={lang} />
          </div>
          <h1 className="request__title serif" id={`${id}-title`}>
            {content.title}
          </h1>
          <p className="request__lead">{content.lead}</p>
          {updated && (
            <p className="legal__updated">
              {es ? 'Última actualización: ' : 'Last updated: '}
              <time dateTime={updated}>{formatLegalDate(updated, lang)}</time>
            </p>
          )}
        </header>

        {toc && (
          <nav className="legal__toc reveal" aria-labelledby={`${id}-toc-title`}>
            <h2 className="legal__toc-title" id={`${id}-toc-title`}>
              {es ? 'En esta página' : 'On this page'}
            </h2>
            <ol>
              {content.sections.map((section) => (
                <li key={section.id}>
                  <a href={`#${section.id}`}>{section.title}</a>
                </li>
              ))}
            </ol>
          </nav>
        )}

        {content.sections.map((section, index) => (
          <section
            key={section.id}
            className="legal__section reveal"
            id={section.id}
            aria-labelledby={`${section.id}-title`}
          >
            <h2 id={`${section.id}-title`}>
              {toc && (
                <>
                  <span className="legal__num">{index + 1}.</span>{' '}
                </>
              )}
              {section.title}
            </h2>
            {section.body}
          </section>
        ))}

        <div className="legal__actions reveal">
          <ButtonLink to="/request-appointment" variant="primary">
            {es ? 'Solicitar una cita' : 'Request an appointment'}
          </ButtonLink>
          <ButtonLink to="/" variant="quiet">
            {es ? 'Volver al inicio' : 'Back to home'}
          </ButtonLink>
        </div>
      </div>
    </article>
  );
}
