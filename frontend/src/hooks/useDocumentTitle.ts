import { useEffect } from 'react';

const SUFFIX = 'PsiClinic';

export function useDocumentTitle(title: string | undefined, clinicName = SUFFIX): void {
  useEffect(() => {
    if (title === undefined) return;
    document.title = title ? `${title} · ${clinicName}` : clinicName;
  }, [title, clinicName]);
}

/** Updates the description and canonical link for public pages. */
export function useMetaDescription(description: string): void {
  useEffect(() => {
    document.querySelector('meta[name="description"]')?.setAttribute('content', description);
    document.querySelector('meta[property="og:description"]')?.setAttribute('content', description);
    let canonical = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (!canonical) {
      canonical = document.createElement('link');
      canonical.rel = 'canonical';
      document.head.appendChild(canonical);
    }
    canonical.href = window.location.origin + window.location.pathname;
  }, [description]);
}
