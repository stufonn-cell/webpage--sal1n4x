/**
 * Editorial copy for the public site, kept in one place so it can be
 * reviewed without touching components.
 *
 * Rule of thumb: every claim rests on something the system already does or on
 * documents the clinic already uses (consents, instruments, intervention
 * catalog). There are no testimonials, success figures or clinical promises:
 * in psychology those claims require professional backing.
 */

import type { IconName } from '@/components/ui/Icon';

export interface Service {
  id: string;
  title: string;
  short: string;
  forWhom: string;
  how: string[];
  modality: string;
  icon: IconName;
}

export const SERVICES: Service[] = [
  {
    id: 'adults',
    title: 'Therapy for adults',
    short: 'A one-on-one space to understand what you are going through and find ways to cope with it.',
    forWhom:
      'Adults going through a hard time: anxiety, lasting sadness, stress, life changes or difficulties in their relationships.',
    how: [
      'A first conversation to learn what brings you here and what you hope to get from the process.',
      'Goals agreed on with you and reviewed along the way.',
      'Regular sessions with practical tools to try out between one session and the next.',
    ],
    modality: 'In person or online',
    icon: 'user',
  },
  {
    id: 'children',
    title: 'Children and teens',
    short: 'Support adapted to each age, with the family as part of the process.',
    forWhom:
      'Minors and their families, with the consent of their legal guardian. We protect the confidential space each process needs.',
    how: [
      'A first meeting with the family to understand the situation.',
      'Sessions with the child or teen, in language that fits their age.',
      'Guidance sessions with the family when the process calls for it.',
    ],
    modality: 'In person or online, depending on age',
    icon: 'heart',
  },
  {
    id: 'assessment',
    title: 'Psychological assessment',
    short: 'Standardized questionnaires that help us understand how you are doing and follow your progress.',
    forWhom:
      'Anyone starting a process, or who wants a clear reference point for their emotional wellbeing over time.',
    how: [
      'Internationally used instruments on mood, anxiety, stress, self-esteem and wellbeing.',
      'You can answer them in session or from your portal, calmly and from home.',
      'The results guide the conversation with your professional: they do not replace their judgment and they are not a diagnosis.',
    ],
    modality: 'In session or from the patient portal',
    icon: 'chart',
  },
  {
    id: 'online',
    title: 'Online care',
    short: 'Sessions by video call or phone, with the same care as in the office.',
    forWhom: 'People who live far away, have tight schedules or feel more comfortable talking from home.',
    how: [
      'The link for each session is waiting for you in your patient portal.',
      'We recommend a private place and a stable connection.',
      'Before you start, you sign a consent form that explains what online care can and cannot do.',
    ],
    modality: 'Video call or phone',
    icon: 'video',
  },
];

export const STEPS = [
  {
    title: 'You write to us',
    text: 'Tell us who the appointment is for and when works best for you. You do not need to share personal details in the form.',
  },
  {
    title: 'We get in touch',
    text: 'We write or call you, however you prefer, to answer your questions and agree on a time.',
  },
  {
    title: 'First session',
    text: 'We talk about what brings you here and decide together whether this space is the right fit for you.',
  },
];

export const PRIVACY_POINTS: { title: string; text: string; icon: IconName }[] = [
  {
    title: 'Professional confidentiality',
    text: 'What you share in session is confidential. It can only be lifted if there is a risk to your life or someone else’s, or by order of a court.',
    icon: 'lock',
  },
  {
    title: 'Your data, for your care',
    text: 'We use your information only to care for you, bill for our services and comply with the law. You can ask to access, correct or delete it whenever you like.',
    icon: 'shield',
  },
  {
    title: 'Controlled access',
    text: 'Only the team caring for you can see your clinical record, and every time someone opens it, it is logged.',
    icon: 'clipboard',
  },
];

export interface Faq {
  question: string;
  answer: string;
}

export function buildFaqs(sessionMinutes: string, crisisLine: string): Faq[] {
  const minutes = Number(sessionMinutes) > 0 ? sessionMinutes : '50';
  return [
    {
      question: 'How long is a session?',
      answer: `Each session lasts about ${minutes} minutes. You and your professional agree on how often to meet, based on what you need.`,
    },
    {
      question: 'Can I have my sessions by video call?',
      answer:
        'Yes. We offer sessions by video call or phone. We ask you to find a private place for the session, and before you start you sign a consent form specific to online care.',
    },
    {
      question: 'Is what I share confidential?',
      answer:
        'Yes, it is protected by professional confidentiality. There are only two exceptions: a risk to your life or someone else’s, or a court order. We explain this to you in writing before you start.',
    },
    {
      question: 'Do you see children and teens?',
      answer:
        'Yes. We need the consent of their legal guardian. The family takes part in the process when it helps, and we respect the confidential space the young person needs.',
    },
    {
      question: 'What happens after I send my request?',
      answer:
        'A request is not yet a confirmed appointment. Someone from our team reviews it and gets in touch with you, the way you chose, to agree on a day and time.',
    },
    {
      question: 'Can I stop whenever I want?',
      answer:
        'Yes. You can pause or end the process at any time, ask for a copy of your clinical record and ask about any part of the process.',
    },
    {
      question: 'How much does it cost?',
      answer:
        'Fees depend on the type of care. You can ask about them in your request or by phone, and we will share them with you before scheduling.',
    },
    {
      question: 'Do you handle emergencies?',
      answer: `We are not an emergency service. If you or someone close to you is in danger, call the emergency line ${crisisLine} right away or go to the nearest emergency room.`,
    },
  ];
}

export const DEFAULT_ABOUT =
  'We support individuals and families with calm, respect and confidentiality. Every process starts by listening to you.';
