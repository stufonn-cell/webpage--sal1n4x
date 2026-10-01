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

/** WhatsApp link built from the number saved in settings. */
export function whatsappLink(number: string, text = 'Hi, I would like some information about booking an appointment.'): string {
  return `https://wa.me/${number.replace(/\D+/g, '')}?text=${encodeURIComponent(text)}`;
}

export function telLink(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, '')}`;
}
