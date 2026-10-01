/**
 * Textos editoriales del sitio publico, en un solo lugar para poder
 * revisarlos sin tocar componentes.
 *
 * Criterio: cada afirmacion se apoya en algo que el sistema ya hace o en los
 * documentos que la clinica ya usa (consentimientos, instrumentos, catalogo
 * de intervenciones). No hay testimonios, cifras de exito ni promesas
 * clinicas: en psicologia esas afirmaciones requieren respaldo profesional.
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
    id: 'adultos',
    title: 'Psicoterapia para adultos',
    short: 'Un espacio individual para entender lo que estás viviendo y encontrar formas de afrontarlo.',
    forWhom:
      'Personas adultas que atraviesan un momento difícil: ansiedad, tristeza persistente, estrés, cambios vitales o dificultades en sus relaciones.',
    how: [
      'Una primera conversación para conocer lo que te trae y lo que esperas del proceso.',
      'Objetivos acordados contigo y revisados a lo largo del camino.',
      'Sesiones periódicas con herramientas concretas para practicar entre una y otra.',
    ],
    modality: 'Presencial o virtual',
    icon: 'user',
  },
  {
    id: 'infancia',
    title: 'Niñas, niños y adolescentes',
    short: 'Acompañamiento adaptado a cada edad, con la familia como parte del proceso.',
    forWhom:
      'Menores de edad y sus familias, con la autorización de su representante legal. Cuidamos los espacios de confidencialidad que cada proceso necesita.',
    how: [
      'Un primer encuentro con la familia para entender la situación.',
      'Sesiones con la niña, el niño o adolescente en un lenguaje cercano a su edad.',
      'Encuentros de orientación con la familia cuando el proceso lo requiere.',
    ],
    modality: 'Presencial o virtual según la edad',
    icon: 'heart',
  },
  {
    id: 'evaluacion',
    title: 'Evaluación psicológica',
    short: 'Cuestionarios estandarizados que ayudan a conocer cómo estás y a seguir tu evolución.',
    forWhom:
      'Quienes inician un proceso o quieren tener una referencia clara de su bienestar emocional a lo largo del tiempo.',
    how: [
      'Instrumentos de uso internacional sobre estado de ánimo, ansiedad, estrés, autoestima y bienestar.',
      'Puedes responderlos en consulta o desde tu portal, con calma y desde tu casa.',
      'Los resultados orientan la conversación con tu profesional: no reemplazan su criterio ni son un diagnóstico.',
    ],
    modality: 'En consulta o desde el portal del paciente',
    icon: 'chart',
  },
  {
    id: 'virtual',
    title: 'Atención virtual',
    short: 'Sesiones por videollamada o teléfono, con el mismo cuidado que en el consultorio.',
    forWhom: 'Personas que viven lejos, tienen horarios difíciles o se sienten más cómodas conversando desde su casa.',
    how: [
      'El enlace de cada sesión queda disponible en tu portal de paciente.',
      'Te recomendamos un lugar privado y una conexión estable.',
      'Antes de empezar firmas un consentimiento que explica sus alcances y sus límites.',
    ],
    modality: 'Videollamada o teléfono',
    icon: 'video',
  },
];

export const STEPS = [
  {
    title: 'Nos escribes',
    text: 'Cuéntanos para quién es la cita y cuándo te queda mejor. No necesitas explicar detalles personales en el formulario.',
  },
  {
    title: 'Te contactamos',
    text: 'Te escribimos o llamamos por el medio que prefieras para resolver tus dudas y acordar un horario.',
  },
  {
    title: 'Primera sesión',
    text: 'Conversamos sobre lo que te trae y definimos juntos si este espacio es el adecuado para ti.',
  },
];

export const PRIVACY_POINTS: { title: string; text: string; icon: IconName }[] = [
  {
    title: 'Secreto profesional',
    text: 'Lo que compartes en sesión es confidencial. Solo puede levantarse si existe riesgo para tu vida o la de otras personas, o por orden de una autoridad judicial.',
    icon: 'lock',
  },
  {
    title: 'Tus datos, para tu atención',
    text: 'Usamos tu información solo para atenderte, facturar y cumplir la ley. Puedes pedir acceso, corrección o supresión cuando quieras.',
    icon: 'shield',
  },
  {
    title: 'Acceso controlado',
    text: 'Tu historia clínica solo la ve el equipo que te atiende, y cada consulta a ella queda registrada.',
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
      question: '¿Cuánto dura una sesión?',
      answer: `Cada sesión dura alrededor de ${minutes} minutos. La frecuencia la acuerdas con tu profesional según lo que necesites.`,
    },
    {
      question: '¿Puedo tener las sesiones por videollamada?',
      answer:
        'Sí. Atendemos por videollamada o teléfono. Te pedimos ubicarte en un lugar privado durante la sesión, y antes de empezar firmas un consentimiento específico para la atención virtual.',
    },
    {
      question: '¿Lo que cuento es confidencial?',
      answer:
        'Sí, lo protege el secreto profesional. Solo hay dos excepciones: que exista riesgo para tu vida o la de otra persona, o que una autoridad judicial lo exija. Antes de empezar te explicamos esto por escrito.',
    },
    {
      question: '¿Atienden a niñas, niños y adolescentes?',
      answer:
        'Sí. Necesitamos la autorización de su representante legal. La familia participa en el proceso cuando es útil, y respetamos los espacios de confidencialidad que la persona menor de edad necesite.',
    },
    {
      question: '¿Qué pasa después de enviar la solicitud?',
      answer:
        'La solicitud todavía no es una cita confirmada. Una persona del equipo la revisa y se comunica contigo por el medio que elegiste para acordar día y hora.',
    },
    {
      question: '¿Puedo dejar el proceso cuando quiera?',
      answer:
        'Sí. Puedes pausarlo o terminarlo en cualquier momento, pedir copia de tu historia clínica y preguntar por cualquier parte del proceso.',
    },
    {
      question: '¿Cuánto cuesta?',
      answer:
        'Las tarifas dependen del tipo de atención. Puedes preguntarlas en tu solicitud o por teléfono y te las compartimos antes de agendar.',
    },
    {
      question: '¿Atienden urgencias?',
      answer: `No somos un servicio de urgencias. Si tú o alguien cercano está en peligro, comunícate de inmediato con la línea de emergencias ${crisisLine} o acude al servicio de urgencias más cercano.`,
    },
  ];
}

export const DEFAULT_ABOUT =
  'Acompañamos procesos individuales y familiares con calma, respeto y confidencialidad. Cada proceso empieza por escucharte.';
