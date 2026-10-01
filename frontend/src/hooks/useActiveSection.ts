import { useEffect, useState } from 'react';

/** Devuelve el id de la seccion que ocupa la franja central de la pantalla. */
export function useActiveSection(ids: string[]): string {
  const [active, setActive] = useState('');
  const key = ids.join('|');

  useEffect(() => {
    if (!('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((entry) => entry.isIntersecting);
        if (visible.length > 0) setActive(visible[0].target.id);
      },
      { rootMargin: '-45% 0px -50% 0px' },
    );

    key.split('|').forEach((id) => {
      const element = document.getElementById(id);
      if (element) observer.observe(element);
    });

    return () => observer.disconnect();
  }, [key]);

  return active;
}
