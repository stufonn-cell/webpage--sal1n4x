import { useQuery } from '@tanstack/react-query';
import { get } from '@/lib/api';
import type { PublicSite } from '@/lib/types';

export function useSite() {
  return useQuery({
    queryKey: ['public-site'],
    queryFn: () => get<PublicSite>('/api/public/site'),
    staleTime: 10 * 60_000,
  });
}

/** Enlace de WhatsApp a partir del numero guardado en la configuracion. */
export function whatsappLink(number: string, text = 'Hola, quisiera información para agendar una cita.'): string {
  return `https://wa.me/${number.replace(/\D+/g, '')}?text=${encodeURIComponent(text)}`;
}

export function telLink(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, '')}`;
}
