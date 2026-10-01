import type { DocumentLanguage } from './types';

/**
 * Fixed wording of the documents handed to patients (informed consents,
 * printed session notes, invoices and assessment reports). The interface is
 * English; these documents can also be produced in Spanish. Both languages
 * must define every key: the `satisfies` check below enforces it.
 */

const en = {
  locale: 'en-US',
  languageName: 'English',
  patient: 'Patient',
  recordNumber: 'Record number',
  idDocument: 'ID document',
  age: 'Age',
  yearsOld: (years: number) => `${years} years old`,
  professionalLicense: 'Professional license',

  note: {
    title: (session: number) => `Session note ${session}`,
    session: (session: number) => `Session ${session}`,
    formats: { soap: 'SOAP', dap: 'DAP', free: 'Free-form' } as Record<string, string>,
    sections: {
      subjective: 'Subjective',
      objective: 'Objective',
      assessment: 'Assessment',
      plan: 'Plan',
      data: 'Data',
      free: 'Session note',
    },
    interventions: 'Interventions',
    homework: 'Agreed homework',
    interventionNames: {} as Record<string, string>,
    mood: 'Perceived mood',
    risk: 'Risk',
    riskLevels: { none: 'none', low: 'low', moderate: 'moderate', high: 'high' } as Record<string, string>,
    signed: 'Signed',
    draft: 'Draft',
    signedOn: (date: string) => `Signed electronically on ${date}`,
  },

  invoice: {
    title: (number: string) => `Invoice ${number}`,
    issuedOn: 'Issued on',
    dueOn: 'Due on',
    item: 'Item',
    quantity: 'Qty.',
    price: 'Price',
    amount: 'Amount',
    subtotal: 'Subtotal',
    tax: 'Tax',
    total: 'Total',
    balance: 'Balance due',
    statuses: { draft: 'Draft', issued: 'Issued', paid: 'Paid', void: 'Void' } as Record<string, string>,
  },

  assessment: {
    administeredOn: (date: string) => `administered on ${date}`,
    result: 'Result',
    clinicianNotes: 'Clinician notes',
    disclaimer: "For guidance only. This is not a diagnosis and doesn't replace clinical evaluation.",
    progress: 'Progress',
    administrations: (count: number) => `${count} administrations`,
    overTime: (code: string) => `${code} over time`,
    answers: 'Item-by-item answers',
    critical: 'critical',
    bands: 'Reference bands',
    criticalTitle: 'Critical items answered positively',
  },

  consent: {
    signed: 'Signed',
    pending: 'Awaiting signature',
    recordedSignature: 'Recorded signature',
    signedOn: (date: string) => `Signed on ${date}`,
    from: (ip: string) => `from ${ip}`,
    yourSignature: 'Your signature',
    signWithPatient: 'Sign with the patient',
    readCalmly: 'Take all the time you need to read it. If anything is unclear, ask your psychologist before you sign.',
    fullName: 'Full name',
    signature: 'Signature',
    sign: 'Sign consent',
    signing: 'Recording the signature',
    enterName: 'Enter the full name.',
    drawSignature: 'Draw the signature in the box.',
    done: 'Consent signed.',
  },

  signaturePad: {
    drawn: 'Drawn signature',
    box: 'Signature box',
    placeholder: 'Sign here with your finger or mouse',
    hint: 'Your signature is saved as a drawing, along with the date and time.',
    clear: 'Clear and redo',
  },
};

type DocumentText = typeof en;

const es = {
  locale: 'es-CO',
  languageName: 'Español',
  patient: 'Paciente',
  recordNumber: 'Historia clínica',
  idDocument: 'Documento de identidad',
  age: 'Edad',
  yearsOld: (years: number) => `${years} años`,
  professionalLicense: 'Tarjeta profesional',

  note: {
    title: (session: number) => `Nota de la sesión ${session}`,
    session: (session: number) => `Sesión ${session}`,
    formats: { soap: 'SOAP', dap: 'DAP', free: 'Formato libre' },
    sections: {
      subjective: 'Subjetivo',
      objective: 'Objetivo',
      assessment: 'Análisis',
      plan: 'Plan',
      data: 'Datos',
      free: 'Nota de sesión',
    },
    interventions: 'Intervenciones',
    homework: 'Tareas acordadas',
    interventionNames: {
      'Cognitive restructuring': 'Reestructuración cognitiva',
      'Behavioral activation': 'Activación conductual',
      'Graded exposure': 'Exposición gradual',
      'Relaxation training': 'Entrenamiento en relajación',
      Mindfulness: 'Mindfulness',
      'Emotion regulation': 'Regulación emocional',
      'Social skills training': 'Entrenamiento en habilidades sociales',
      Psychoeducation: 'Psicoeducación',
      'Acceptance and commitment therapy': 'Terapia de aceptación y compromiso',
      'Motivational interviewing': 'Entrevista motivacional',
    },
    mood: 'Estado de ánimo percibido',
    risk: 'Riesgo',
    riskLevels: { none: 'sin riesgo', low: 'bajo', moderate: 'moderado', high: 'alto' },
    signed: 'Firmada',
    draft: 'Borrador',
    signedOn: (date: string) => `Firmada electrónicamente el ${date}`,
  },

  invoice: {
    title: (number: string) => `Factura ${number}`,
    issuedOn: 'Fecha de expedición',
    dueOn: 'Fecha de vencimiento',
    item: 'Concepto',
    quantity: 'Cant.',
    price: 'Valor unitario',
    amount: 'Valor',
    subtotal: 'Subtotal',
    tax: 'Impuestos',
    total: 'Total',
    balance: 'Saldo pendiente',
    statuses: { draft: 'Borrador', issued: 'Emitida', paid: 'Pagada', void: 'Anulada' },
  },

  assessment: {
    administeredOn: (date: string) => `aplicado el ${date}`,
    result: 'Resultado',
    clinicianNotes: 'Notas del profesional',
    disclaimer: 'Resultado orientativo. No constituye un diagnóstico ni reemplaza la valoración clínica.',
    progress: 'Evolución',
    administrations: (count: number) => `${count} aplicaciones`,
    overTime: (code: string) => `Evolución del ${code}`,
    answers: 'Respuestas ítem por ítem',
    critical: 'ítem crítico',
    bands: 'Bandas de referencia',
    criticalTitle: 'Ítems críticos respondidos afirmativamente',
  },

  consent: {
    signed: 'Firmado',
    pending: 'Pendiente de firma',
    recordedSignature: 'Firma registrada',
    signedOn: (date: string) => `Firmado el ${date}`,
    from: (ip: string) => `desde ${ip}`,
    yourSignature: 'Tu firma',
    signWithPatient: 'Firmar con el paciente',
    readCalmly: 'Tómate el tiempo que necesites para leerlo. Si algo no es claro, pregúntale a tu psicólogo antes de firmar.',
    fullName: 'Nombre completo',
    signature: 'Firma',
    sign: 'Firmar consentimiento',
    signing: 'Registrando la firma',
    enterName: 'Escribe el nombre completo.',
    drawSignature: 'Dibuja la firma en el recuadro.',
    done: 'Consentimiento firmado.',
  },

  signaturePad: {
    drawn: 'Firma dibujada',
    box: 'Recuadro para firmar',
    placeholder: 'Firma aquí con el dedo o el mouse',
    hint: 'Tu firma se guarda como trazo, junto con la fecha y la hora.',
    clear: 'Borrar y repetir',
  },
} satisfies DocumentText;

const TEXT: Record<DocumentLanguage, DocumentText> = { en, es };

export function documentText(language: DocumentLanguage | string | null | undefined): DocumentText {
  return language === 'es' ? TEXT.es : TEXT.en;
}

export function isDocumentLanguage(value: string | null | undefined): value is DocumentLanguage {
  return value === 'en' || value === 'es';
}

/** Intervention names come from the English catalog; free text is kept as written. */
export function translateInterventions(value: string, language: DocumentLanguage): string {
  const names = documentText(language).note.interventionNames;
  return value
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => names[part] ?? part)
    .join(', ');
}
