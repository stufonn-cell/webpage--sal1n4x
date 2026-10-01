import { useEffect, type RefObject } from 'react';

/**
 * Marca como visibles los elementos `.reveal` dentro de `root` cuando entran
 * en pantalla. Se anima una sola vez; con movimiento reducido no hace nada.
 */
export function useReveal(root: RefObject<HTMLElement | null>): void {
  useEffect(() => {
    const container = root.current;
    if (!container) return;

    const items = Array.from(container.querySelectorAll<HTMLElement>('.reveal'));
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduced || !('IntersectionObserver' in window)) {
      items.forEach((item) => item.classList.add('is-visible'));
      return;
    }

    document.documentElement.classList.add('js-motion');

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        }
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );

    items.forEach((item) => observer.observe(item));
    return () => observer.disconnect();
  });
}
