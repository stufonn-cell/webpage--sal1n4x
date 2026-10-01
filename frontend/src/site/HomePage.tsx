import { useEffect, useMemo, useRef } from 'react';
import { useDocumentTitle, useMetaDescription } from '@/hooks/useDocumentTitle';
import { useReveal } from '@/hooks/useReveal';
import type { PublicSite } from '@/lib/types';
import { buildFaqs, DEFAULT_ABOUT } from './content';
import { Contact } from './sections/Contact';
import { Faq } from './sections/Faq';
import { Hero } from './sections/Hero';
import { Process } from './sections/Process';
import { Services } from './sections/Services';
import { Team } from './sections/Team';
import { Trust } from './sections/Trust';
import { useSite } from './useSite';

/** Structured data (schema.org) for search engines. */
function useStructuredData(site: PublicSite | undefined) {
  useEffect(() => {
    if (!site) return;
    const { clinic } = site;
    const data = {
      '@context': 'https://schema.org',
      '@type': 'MedicalBusiness',
      name: clinic.clinic_name,
      description: clinic.clinic_about || clinic.clinic_tagline,
      url: window.location.origin,
      inLanguage: 'en',
      telephone: clinic.clinic_phone || undefined,
      email: clinic.clinic_email || undefined,
      address: clinic.clinic_address || undefined,
      employee: site.professionals.map((person) => ({
        '@type': 'Person',
        name: person.full_name,
        jobTitle: person.specialty || 'Psychology',
      })),
    };
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(data);
    document.head.appendChild(script);
    return () => script.remove();
  }, [site]);
}

export default function HomePage() {
  const { data: site } = useSite();
  const root = useRef<HTMLDivElement>(null);
  const name = site?.clinic.clinic_name || 'PsiClinic';

  useDocumentTitle('', `${name} · Psychological care`);
  useMetaDescription(site?.clinic.clinic_about || DEFAULT_ABOUT);
  useStructuredData(site);
  useReveal(root);

  const faqs = useMemo(
    () => buildFaqs(site?.clinic.session_duration ?? '50', site?.clinic.crisis_line || '123'),
    [site?.clinic.session_duration, site?.clinic.crisis_line],
  );

  return (
    <div ref={root}>
      <Hero site={site} />
      <Services site={site} />
      <Process />
      <Team professionals={site?.professionals ?? []} />
      <Trust />
      <Faq items={faqs} />
      <Contact site={site} />
    </div>
  );
}
