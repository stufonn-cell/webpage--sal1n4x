import { useRef } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { useDocumentTitle, useMetaDescription } from '@/hooks/useDocumentTitle';
import { useReveal } from '@/hooks/useReveal';
import { useSite } from './useSite';

/**
 * Aviso de privacidad. El contenido resume las plantillas de consentimiento
 * que la clinica ya usa ("datos" y "general") y describe lo que el sistema
 * hace realmente con la informacion del formulario.
 */
export default function PrivacyPage() {
  const { data: site } = useSite();
  const root = useRef<HTMLDivElement>(null);
  const clinic = site?.clinic;
  const name = clinic?.clinic_name || 'el consultorio';

  useDocumentTitle('Privacidad y datos', clinic?.clinic_name);
  useMetaDescription('Cómo tratamos tus datos personales y de salud, y cómo protegemos la confidencialidad de tu proceso.');
  useReveal(root);

  return (
    <article className="legal" ref={root} aria-labelledby="privacidad-title">
      <div className="container container--narrow">
        <header className="legal__head enter">
          <p className="eyebrow">Privacidad</p>
          <h1 className="request__title serif" id="privacidad-title">
            Cómo cuidamos tu información
          </h1>
          <p className="request__lead">
            Sabemos que hablar de salud mental implica confianza. Aquí te contamos, en palabras sencillas, qué datos
            usamos, para qué y qué derechos tienes.
          </p>
        </header>

        <section className="legal__section reveal">
          <h2>Para qué usamos tus datos</h2>
          <p>
            Usamos tus datos personales y tus datos sensibles de salud con una finalidad exclusiva: prestarte el servicio
            de atención psicológica, facturarlo y cumplir las obligaciones legales que aplican a las historias clínicas.
          </p>
        </section>

        <section className="legal__section reveal">
          <h2>Cuando envías una solicitud de cita</h2>
          <ul>
            <li>Guardamos tu nombre, tus datos de contacto y tus preferencias de horario y modalidad.</li>
            <li>Solo el equipo de {name} puede verla, y lo usa únicamente para contactarte.</li>
            <li>No guardamos tu dirección IP: solo una huella cifrada que sirve para evitar envíos masivos.</li>
            <li>No usamos cookies de publicidad ni herramientas de seguimiento de terceros.</li>
          </ul>
        </section>

        <section className="legal__section reveal">
          <h2>Secreto profesional</h2>
          <p>
            La información que compartes en sesión está protegida por el secreto profesional. Solo puede levantarse cuando
            existe riesgo para tu vida o la de otras personas, o por requerimiento de una autoridad judicial competente.
          </p>
        </section>

        <section className="legal__section reveal">
          <h2>Tus derechos</h2>
          <p>
            Puedes pedir acceso, corrección, actualización o supresión de tus datos, y solicitar copia de tu historia
            clínica. Los datos de la historia se conservan durante el tiempo que exige la normativa aplicable.
          </p>
          {clinic?.clinic_email && (
            <p>
              Para ejercer estos derechos escríbenos a <a href={`mailto:${clinic.clinic_email}`}>{clinic.clinic_email}</a>.
            </p>
          )}
        </section>

        <section className="legal__section reveal">
          <h2>Seguridad</h2>
          <p>
            El acceso a la historia clínica está limitado por rol: cada profesional ve lo que necesita para atenderte, y
            cada consulta, cambio o descarga queda registrada en un historial de auditoría. Las notas de sesión firmadas
            no pueden modificarse.
          </p>
        </section>

        <div className="legal__actions reveal">
          <ButtonLink to="/solicitar-cita" variant="primary">
            Solicitar una cita
          </ButtonLink>
          <ButtonLink to="/" variant="quiet">
            Volver al inicio
          </ButtonLink>
        </div>
      </div>
    </article>
  );
}
