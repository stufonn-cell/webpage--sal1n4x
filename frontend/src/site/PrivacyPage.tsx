import { clinicInfo, LegalDocument, useLegalLang, type ClinicInfo, type LegalContent } from './LegalDocument';
import { useSite } from './useSite';

/**
 * Privacy notice, in English or Spanish (`?lang=es`). The content summarizes
 * the consent templates the clinic already uses ("data" and "general") and
 * describes what the system actually does with the information from the form.
 */
export default function PrivacyPage() {
  const { data: site } = useSite();
  const lang = useLegalLang();
  const info = clinicInfo(site);

  return (
    <LegalDocument
      id="privacy"
      lang={lang}
      content={lang === 'es' ? spanishPrivacy(info) : englishPrivacy(info)}
      clinicName={site?.clinic.clinic_name}
    />
  );
}

function englishPrivacy(info: ClinicInfo): LegalContent {
  const name = info.name || 'the practice';

  return {
    documentTitle: 'Privacy and data',
    description: 'How we handle your personal and health data, and how we protect the confidentiality of your process.',
    eyebrow: 'Privacy',
    title: 'How we look after your information',
    lead: 'We know that talking about mental health takes trust. Here we explain, in plain words, what data we use, what we use it for and what rights you have.',
    sections: [
      {
        id: 'data-use',
        title: 'What we use your data for',
        body: (
          <p>
            We use your personal data and your sensitive health data for one purpose only: to provide you with
            psychological care, bill for it and meet the legal obligations that apply to clinical records.
          </p>
        ),
      },
      {
        id: 'appointment-requests',
        title: 'When you send an appointment request',
        body: (
          <ul>
            <li>We keep your name, your contact details and your preferred times and type of care.</li>
            <li>Only the team at {name} can see it, and they use it only to contact you.</li>
            <li>We do not store your IP address: only an encrypted fingerprint that helps prevent bulk submissions.</li>
            <li>We do not use advertising cookies or third-party tracking tools.</li>
          </ul>
        ),
      },
      {
        id: 'confidentiality',
        title: 'Professional confidentiality',
        body: (
          <p>
            What you share in session is protected by professional confidentiality. It can only be lifted when there is a
            risk to your life or someone else’s, or when a competent court requires it.
          </p>
        ),
      },
      {
        id: 'your-rights',
        title: 'Your rights',
        body: (
          <>
            <p>
              You can ask to access, correct, update or delete your data, and request a copy of your clinical record.
              Clinical record data is kept for as long as the applicable regulations require.
            </p>
            {info.email && (
              <p>
                To exercise these rights, write to us at <a href={`mailto:${info.email}`}>{info.email}</a>.
              </p>
            )}
          </>
        ),
      },
      {
        id: 'security',
        title: 'Security',
        body: (
          <p>
            Access to the clinical record is limited by role: each professional sees what they need to care for you, and
            every view, change or download is recorded in an audit log. Signed session notes cannot be changed.
          </p>
        ),
      },
    ],
  };
}

function spanishPrivacy(info: ClinicInfo): LegalContent {
  return {
    documentTitle: 'Privacidad y datos',
    description: 'Cómo tratamos tus datos personales y de salud, y cómo protegemos la confidencialidad de tu proceso.',
    eyebrow: 'Privacidad',
    title: 'Cómo cuidamos tu información',
    lead: 'Sabemos que hablar de salud mental implica confianza. Aquí te contamos, en palabras sencillas, qué datos usamos, para qué los usamos y qué derechos tienes.',
    sections: [
      {
        id: 'data-use',
        title: 'Para qué usamos tus datos',
        body: (
          <p>
            Usamos tus datos personales y tus datos sensibles de salud con una única finalidad: prestarte el servicio de
            atención psicológica, facturarlo y cumplir las obligaciones legales que aplican a las historias clínicas.
          </p>
        ),
      },
      {
        id: 'appointment-requests',
        title: 'Cuando envías una solicitud de cita',
        body: (
          <ul>
            <li>Guardamos tu nombre, tus datos de contacto y tus preferencias de horario y de modalidad de atención.</li>
            <li>
              {info.name ? `Solo el equipo de ${info.name} puede verla` : 'Solo nuestro equipo puede verla'}, y la usa
              únicamente para contactarte.
            </li>
            <li>No guardamos tu dirección IP: solo una huella cifrada que nos ayuda a evitar envíos masivos.</li>
            <li>No usamos cookies publicitarias ni herramientas de seguimiento de terceros.</li>
          </ul>
        ),
      },
      {
        id: 'confidentiality',
        title: 'Secreto profesional',
        body: (
          <p>
            Lo que compartes en sesión está protegido por el secreto profesional. Solo puede levantarse cuando existe un
            riesgo para tu vida o la de otras personas, o cuando una autoridad judicial competente lo exige.
          </p>
        ),
      },
      {
        id: 'your-rights',
        title: 'Tus derechos',
        body: (
          <>
            <p>
              Puedes pedir acceso, corrección, actualización o supresión de tus datos, y solicitar una copia de tu historia
              clínica. Los datos de la historia clínica se conservan durante el tiempo que exige la normativa aplicable.
            </p>
            {info.email && (
              <p>
                Para ejercer estos derechos, escríbenos a <a href={`mailto:${info.email}`}>{info.email}</a>.
              </p>
            )}
          </>
        ),
      },
      {
        id: 'security',
        title: 'Seguridad',
        body: (
          <p>
            El acceso a la historia clínica está limitado por rol: cada profesional ve lo que necesita para atenderte, y
            cada consulta, cambio o descarga queda registrada en un historial de auditoría. Las notas de sesión firmadas no
            se pueden modificar.
          </p>
        ),
      },
    ],
  };
}
