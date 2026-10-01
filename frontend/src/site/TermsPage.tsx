import { Link } from 'react-router';
import {
  clinicInfo,
  ContactDetails,
  CrisisLines,
  legalHref,
  LegalDocument,
  useLegalLang,
  type ClinicInfo,
  type LegalContent,
} from './LegalDocument';
import { useSite } from './useSite';

/** Change this date whenever the text of the terms changes. */
const LAST_UPDATED = '2026-10-01';

/**
 * Terms and conditions for the public site, appointment requests, the patient
 * portal and sessions. Both versions follow the same structure and section ids,
 * so `/terms?lang=es#fees-and-payment` lands on the same section in Spanish.
 */
export default function TermsPage() {
  const { data: site } = useSite();
  const lang = useLegalLang();
  const info = clinicInfo(site);

  return (
    <LegalDocument
      id="terms"
      lang={lang}
      content={lang === 'es' ? spanishTerms(info) : englishTerms(info)}
      clinicName={site?.clinic.clinic_name}
      updated={LAST_UPDATED}
      toc
    />
  );
}

function englishTerms(info: ClinicInfo): LegalContent {
  const name = info.name || 'our practice';
  const privacy = <Link to={legalHref('/privacy', 'en')}>privacy policy</Link>;

  return {
    documentTitle: 'Terms and conditions',
    description:
      'The terms for our website, appointment requests, patient portal and sessions, in person and online. Also available in Spanish.',
    eyebrow: 'Terms and conditions',
    title: 'How we work together',
    lead: 'These terms explain how our website, appointment requests, patient portal and sessions work, and what we ask of you. We wrote them in plain words. If something is not clear, ask us.',
    sections: [
      {
        id: 'about-these-terms',
        title: 'Who we are and what these terms cover',
        body: (
          <>
            <p>
              {info.name ? <>We are {info.name}, a psychology practice in Colombia.</> : <>We are a psychology practice in Colombia.</>}{' '}
              These terms apply when you use our website, send an appointment request, use the patient portal or attend
              sessions with our team, in person or online.
            </p>
            <p>
              In these terms, “we” and “us” mean {name} and its team of professionals. “You” means the person who uses our
              services, or their legal representative.
            </p>
            <p>
              By using these services you accept these terms. Your care is also governed by the informed consent you sign
              before you start and by our {privacy}. If a consent you signed says something more specific, the consent
              applies.
            </p>
          </>
        ),
      },
      {
        id: 'emergencies',
        title: 'We are not an emergency service',
        body: (
          <>
            <p>
              Our website, the request form and the patient portal are not an emergency service, and no one monitors them
              around the clock. We review messages and requests during working hours.
            </p>
            <p className="legal__callout">
              <strong>If you need help right now:</strong> if you or someone else is in danger, or you are going through a
              crisis, do not wait for our reply. <CrisisLines crisisLine={info.crisisLine} lang="en" sentenceStart />, or go
              to the nearest emergency room.
            </p>
          </>
        ),
      },
      {
        id: 'appointments',
        title: 'Appointments',
        body: (
          <ul>
            <li>
              Sending a request does not book an appointment. Someone from our team will contact you to agree on a day and
              time. The appointment is confirmed once we have agreed on it with you.
            </li>
            <li>
              {info.sessionMinutes
                ? `Sessions usually last about ${info.sessionMinutes} minutes. `
                : 'Your professional will tell you how long sessions last. '}
              You and your professional agree on how often to meet, based on what you need.
            </li>
            <li>
              Please arrive, or connect, on time. If you are late, the session may still have to end at the scheduled time
              so the next person is not affected.
            </li>
            <li>
              If you need to cancel or reschedule, let us know at least 24 hours in advance. Our policy is that late
              cancellations and missed sessions without notice may be charged in full or in part, as your professional
              explains to you before you start. We always take real emergencies and events beyond your control into
              account.
            </li>
            <li>
              Sometimes we may need to reschedule, for example if your professional is ill. If that happens, we will tell
              you as soon as possible and offer you another time, at no cost to you.
            </li>
          </ul>
        ),
      },
      {
        id: 'fees-and-payment',
        title: 'Fees and payment',
        body: (
          <ul>
            <li>
              We tell you the fee for your sessions before you start, and we agree on it with you. If it changes, we will
              let you know in advance, before the new fee applies.
            </li>
            <li>You receive an invoice for the services you pay for.</li>
            <li>We tell you which payment methods we accept when we agree on your first appointment.</li>
            <li>
              Sessions that have taken place are not refunded. If you paid in advance for a session that did not take
              place, we agree with you on whether to move it to another date or refund it, in line with the cancellation
              policy above.
            </li>
            <li>
              This website and the patient portal do not process payments and never charge you automatically. We will
              never ask you for card numbers or bank passwords by email, message or phone.
            </li>
          </ul>
        ),
      },
      {
        id: 'online-sessions',
        title: 'Online sessions',
        body: (
          <>
            <p>
              We offer sessions by video call or phone, following the rules for telehealth in Colombia (Law 1419 of 2010
              and Resolution 2654 of 2019). Before your first online session, you sign a consent form specific to this
              type of care.
            </p>
            <ul>
              <li>Find a private, quiet place where no one can overhear you, and use headphones if you can.</li>
              <li>
                Use a stable connection and a device with a camera and microphone. You will find the link for each session
                in your patient portal.
              </li>
              <li>
                Do not record the session or let other people join unless you have agreed on it with your professional. We
                do not record sessions without your written authorization either.
              </li>
              <li>
                If the connection fails, or your professional believes online care is not safe or right for you at that
                moment, they may continue by phone, reschedule the session or suggest seeing you in person.
              </li>
              <li>
                If a crisis comes up during an online session, your professional may contact your emergency contact or the
                emergency services.
              </li>
            </ul>
          </>
        ),
      },
      {
        id: 'confidentiality',
        title: 'Confidentiality and clinical records',
        body: (
          <>
            <p>
              What you share with us is protected by professional confidentiality, as set out in Law 1090 of 2006, which
              regulates the practice of psychology in Colombia. We share your information with others only with your
              permission, or in the limited cases the law allows: when there is a serious risk to your life or someone
              else’s, or when a competent authority, such as a court, orders it. Even then, we share only what is strictly
              necessary.
            </p>
            <p>
              Your clinical record is a confidential document. We keep it for as long as Colombian health regulations
              require, and you can ask for a copy at any time.
            </p>
            <p>
              Our {privacy} explains how we handle your personal data and how to exercise your rights under Law 1581 of
              2012.
            </p>
          </>
        ),
      },
      {
        id: 'minors',
        title: 'Children and teens',
        body: (
          <>
            <p>
              To care for a child or teenager under 18, we need the informed consent of their parents or legal
              representative, in line with Law 1098 of 2006 (Code of Childhood and Adolescence).
            </p>
            <p>We listen to the child or teen and take their opinion into account, according to their age and maturity.</p>
            <p>
              Their legal representative receives the information needed to support the process. At the same time, we
              protect the confidential space the young person needs for therapy to work, unless there is a risk to their
              safety.
            </p>
          </>
        ),
      },
      {
        id: 'patient-portal',
        title: 'Patient portal',
        body: (
          <>
            <p>
              The portal is for people who are already in care with us. Our team creates your account when it is useful for
              your process; you cannot sign up on your own. In the portal you can see your appointments, answer
              questionnaires, read your documents and sign consent forms.
            </p>
            <p>When you use it, we ask you to:</p>
            <ul>
              <li>
                Keep your username and password to yourself, and change the temporary password from your profile the first
                time you log in.
              </li>
              <li>
                Log out when you finish, especially on shared devices. For your security, sessions also close on their own
                after a period of inactivity.
              </li>
              <li>Tell us right away if you think someone else has used your account, or if you see information that is not yours.</li>
              <li>
                Use it only for your own care. Do not try to see other people’s information or parts of the system you do
                not have access to.
              </li>
              <li>
                Do not use bots, scripts or other automated tools to copy content or send large numbers of requests, and do
                not try to attack, overload or get around the security of the site.
              </li>
            </ul>
            <p>
              We may suspend an account that is misused or that puts other people’s information at risk. When your process
              ends, we may close your portal access; you can still ask us for a copy of your documents.
            </p>
          </>
        ),
      },
      {
        id: 'questionnaires',
        title: 'Questionnaires and results',
        body: (
          <>
            <p>
              The questionnaires you answer in session or in the portal, and the scores they produce, are support tools.
              They help your professional understand how you are doing and follow your progress, but they are not a
              diagnosis by themselves. Only your professional, in session and with the full context, can make a clinical
              judgment. If a result worries you, talk to them about it.
            </p>
            <p>
              Your answers are not reviewed in real time. If you are going through a crisis, do not wait: follow the steps
              in <a href="#emergencies">section 2</a>.
            </p>
          </>
        ),
      },
      {
        id: 'intellectual-property',
        title: 'Intellectual property',
        body: (
          <>
            <p>
              The texts, design, logos and other content on this site belong to {name} or are used with permission. You can
              read them and share links to them, but you may not copy, change or use them for commercial purposes without
              our written permission.
            </p>
            <p>
              The software that runs the site and the portal is proprietary and protected by copyright. You may not copy
              it, change it or try to extract its source code. The standardized questionnaires we use belong to their
              respective authors.
            </p>
          </>
        ),
      },
      {
        id: 'liability',
        title: 'Limits of our liability',
        body: (
          <>
            <p>
              We work to keep the site and the portal available, accurate and secure, but we cannot promise they will
              always work without interruptions or errors, for example during maintenance or because of failures at
              internet providers. The general information on this site does not replace a professional consultation.
            </p>
            <p>
              We are not responsible for damage caused by misuse of your account that is not our fault, by events beyond
              our reasonable control, or by the content of third-party sites we link to.
            </p>
            <p>
              Nothing in these terms limits the rights you have as a consumer under Law 1480 of 2011 (Consumer Statute),
              or the responsibility we have for the professional care we provide, as Colombian law sets out.
            </p>
          </>
        ),
      },
      {
        id: 'changes-and-contact',
        title: 'Changes, governing law and contact',
        body: (
          <>
            <p>
              We may update these terms when our services or the law change. The date at the top shows the current
              version. If the changes are important, we will let you know before they apply, for example by email.
            </p>
            <p>
              These terms are governed by the laws of Colombia. If a disagreement comes up, we would like to try to resolve
              it with you directly first. If that is not possible, you can turn to the competent Colombian authorities.
            </p>
            <p>
              These terms are available in English and Spanish. If you notice any difference between the two versions,
              the Spanish version prevails.
            </p>
            <p>If you have questions about these terms, write to us or call us:</p>
            <ContactDetails info={info} lang="en" />
          </>
        ),
      },
    ],
  };
}

function spanishTerms(info: ClinicInfo): LegalContent {
  const name = info.name || 'nuestro consultorio';
  const privacy = <Link to={legalHref('/privacy', 'es')}>política de privacidad</Link>;

  return {
    documentTitle: 'Términos y condiciones',
    description:
      'Las condiciones de nuestro sitio web, las solicitudes de cita, el portal del paciente y las sesiones presenciales y virtuales.',
    eyebrow: 'Términos y condiciones',
    title: 'Cómo trabajamos contigo',
    lead: 'Estos términos explican cómo funcionan nuestro sitio web, las solicitudes de cita, el portal del paciente y las sesiones, y qué esperamos de ti. Los escribimos en palabras sencillas. Si algo no queda claro, pregúntanos.',
    sections: [
      {
        id: 'about-these-terms',
        title: 'Quiénes somos y qué cubren estos términos',
        body: (
          <>
            <p>
              {info.name ? <>Somos {info.name}, un consultorio de psicología en Colombia.</> : <>Somos un consultorio de psicología en Colombia.</>}{' '}
              Estos términos aplican cuando usas nuestro sitio web, envías una solicitud de cita, usas el portal del
              paciente o asistes a sesiones con nuestro equipo, de forma presencial o virtual.
            </p>
            <p>
              Cuando decimos “nosotros”, nos referimos a {name} y a su equipo de profesionales. Cuando decimos “tú”, nos
              referimos a la persona que usa nuestros servicios o a su representante legal.
            </p>
            <p>
              Al usar estos servicios aceptas estos términos. Tu atención también se rige por el consentimiento informado
              que firmas antes de empezar y por nuestra {privacy}. Si un consentimiento que firmaste dice algo más
              específico, prevalece lo que dice el consentimiento.
            </p>
          </>
        ),
      },
      {
        id: 'emergencies',
        title: 'No somos un servicio de urgencias',
        body: (
          <>
            <p>
              Nuestro sitio web, el formulario de solicitud y el portal del paciente no son un servicio de urgencias, y
              nadie los revisa las 24 horas. Revisamos los mensajes y las solicitudes en horario de atención.
            </p>
            <p className="legal__callout">
              <strong>Si necesitas ayuda ahora:</strong> si tú u otra persona están en peligro, o estás atravesando una
              crisis, no esperes nuestra respuesta. <CrisisLines crisisLine={info.crisisLine} lang="es" sentenceStart /> o
              acude al servicio de urgencias más cercano.
            </p>
          </>
        ),
      },
      {
        id: 'appointments',
        title: 'Citas',
        body: (
          <ul>
            <li>
              Enviar una solicitud no reserva una cita. Alguien de nuestro equipo te contactará para acordar el día y la
              hora. La cita queda confirmada cuando la hayamos acordado contigo.
            </li>
            <li>
              {info.sessionMinutes
                ? `Las sesiones duran aproximadamente ${info.sessionMinutes} minutos. `
                : 'Tu profesional te contará cuánto dura cada sesión. '}
              La frecuencia de los encuentros la acuerdas con tu profesional, según lo que necesites.
            </li>
            <li>
              Te pedimos llegar, o conectarte, puntualmente. Si llegas tarde, es posible que la sesión tenga que terminar a
              la hora prevista para no afectar a la siguiente persona.
            </li>
            <li>
              Si necesitas cancelar o cambiar una cita, avísanos con al menos 24 horas de anticipación. Nuestra política es
              que las cancelaciones tardías y las inasistencias sin aviso pueden cobrarse total o parcialmente, según lo
              que tu profesional te explique antes de empezar. Siempre tenemos en cuenta las emergencias reales y los casos
              de fuerza mayor.
            </li>
            <li>
              A veces somos nosotros quienes necesitamos reprogramar, por ejemplo si tu profesional se enferma. En ese caso
              te avisaremos lo antes posible y te ofreceremos otro horario, sin costo para ti.
            </li>
          </ul>
        ),
      },
      {
        id: 'fees-and-payment',
        title: 'Tarifas y pagos',
        body: (
          <ul>
            <li>
              Te informamos la tarifa de tus sesiones antes de empezar y la acordamos contigo. Si cambia, te avisaremos con
              anticipación, antes de que se aplique la nueva tarifa.
            </li>
            <li>Recibes una factura por los servicios que pagas.</li>
            <li>Te contamos qué medios de pago aceptamos cuando acordamos tu primera cita.</li>
            <li>
              No hacemos reembolsos de sesiones ya realizadas. Si pagaste por anticipado una sesión que no se llevó a cabo,
              acordamos contigo si la pasamos a otra fecha o te devolvemos el dinero, según la política de cancelación
              anterior.
            </li>
            <li>
              Este sitio web y el portal del paciente no procesan pagos ni hacen cobros automáticos. Nunca te pediremos
              números de tarjeta ni claves bancarias por correo, mensaje o llamada.
            </li>
          </ul>
        ),
      },
      {
        id: 'online-sessions',
        title: 'Sesiones virtuales',
        body: (
          <>
            <p>
              Ofrecemos sesiones por videollamada o por teléfono, de acuerdo con las normas de telesalud en Colombia (Ley
              1419 de 2010 y Resolución 2654 de 2019). Antes de tu primera sesión virtual firmas un consentimiento
              específico para esta modalidad.
            </p>
            <ul>
              <li>Ubícate en un lugar privado y tranquilo, donde nadie te escuche, y usa audífonos si puedes.</li>
              <li>
                Usa una conexión estable y un dispositivo con cámara y micrófono. Encontrarás el enlace de cada sesión en tu
                portal del paciente.
              </li>
              <li>
                No grabes la sesión ni permitas que otras personas se unan, salvo que lo hayas acordado con tu profesional.
                Nosotros tampoco grabamos sesiones sin tu autorización escrita.
              </li>
              <li>
                Si la conexión falla, o si tu profesional considera que en ese momento la atención virtual no es segura o
                adecuada para ti, podrá continuar por teléfono, reprogramar la sesión o proponerte una cita presencial.
              </li>
              <li>
                Si se presenta una crisis durante una sesión virtual, tu profesional podrá comunicarse con tu contacto de
                emergencia o con los servicios de emergencia.
              </li>
            </ul>
          </>
        ),
      },
      {
        id: 'confidentiality',
        title: 'Confidencialidad e historia clínica',
        body: (
          <>
            <p>
              Lo que compartes con nosotros está protegido por el secreto profesional, en los términos de la Ley 1090 de
              2006, que regula el ejercicio de la psicología en Colombia. Solo compartimos tu información con otras
              personas si tú lo autorizas o en los casos limitados que prevé la ley: cuando existe un riesgo serio para tu
              vida o la de otras personas, o cuando una autoridad competente, como un juez, lo ordena. Aun en esos casos,
              compartimos solo lo estrictamente necesario.
            </p>
            <p>
              Tu historia clínica es un documento reservado. La conservamos durante el tiempo que exige la normativa
              colombiana de salud, y puedes pedir una copia cuando lo necesites.
            </p>
            <p>
              En nuestra {privacy} te explicamos cómo tratamos tus datos personales y cómo ejercer tus derechos según la Ley
              1581 de 2012.
            </p>
          </>
        ),
      },
      {
        id: 'minors',
        title: 'Niñas, niños y adolescentes',
        body: (
          <>
            <p>
              Para atender a una niña, un niño o un adolescente menor de 18 años necesitamos el consentimiento informado de
              sus padres o de su representante legal, de acuerdo con la Ley 1098 de 2006 (Código de la Infancia y la
              Adolescencia).
            </p>
            <p>Escuchamos a la niña, al niño o al adolescente y tenemos en cuenta su opinión, según su edad y madurez.</p>
            <p>
              Su representante legal recibe la información necesaria para acompañar el proceso. Al mismo tiempo, protegemos
              el espacio de confidencialidad que la persona menor de edad necesita para que la terapia funcione, salvo que
              exista un riesgo para su seguridad.
            </p>
          </>
        ),
      },
      {
        id: 'patient-portal',
        title: 'Portal del paciente',
        body: (
          <>
            <p>
              El portal es para las personas que ya están en proceso con nosotros. Nuestro equipo crea tu cuenta cuando es
              útil para tu proceso; no es posible registrarse por cuenta propia. Desde el portal puedes ver tus citas,
              responder cuestionarios, consultar tus documentos y firmar consentimientos.
            </p>
            <p>Cuando lo uses, te pedimos:</p>
            <ul>
              <li>
                Mantener en secreto tu usuario y tu contraseña, y cambiar la contraseña temporal desde tu perfil la primera
                vez que ingreses.
              </li>
              <li>
                Cerrar sesión al terminar, sobre todo en equipos compartidos. Por tu seguridad, la sesión también se cierra
                sola después de un tiempo de inactividad.
              </li>
              <li>Avisarnos de inmediato si crees que alguien más usó tu cuenta o si ves información que no es tuya.</li>
              <li>
                Usarlo solo para tu propia atención, sin intentar ver información de otras personas ni partes del sistema a
                las que no tienes acceso.
              </li>
              <li>
                No usar bots, scripts ni otras herramientas automáticas para copiar contenido o enviar solicitudes masivas,
                ni intentar atacar, sobrecargar o burlar la seguridad del sitio.
              </li>
            </ul>
            <p>
              Podemos suspender una cuenta que se use de forma indebida o que ponga en riesgo la información de otras
              personas. Cuando termine tu proceso, podemos cerrar tu acceso al portal; aun así, puedes pedirnos copia de tus
              documentos.
            </p>
          </>
        ),
      },
      {
        id: 'questionnaires',
        title: 'Cuestionarios y resultados',
        body: (
          <>
            <p>
              Los cuestionarios que respondes en sesión o en el portal, y los puntajes que arrojan, son herramientas de
              apoyo. Ayudan a tu profesional a entender cómo estás y a seguir tu avance, pero por sí solos no son un
              diagnóstico. Solo tu profesional, en sesión y con todo el contexto, puede hacer una valoración clínica. Si un
              resultado te preocupa, conversa con tu profesional.
            </p>
            <p>
              Tus respuestas no se revisan en tiempo real. Si estás atravesando una crisis, no esperes: sigue las
              indicaciones de la <a href="#emergencies">sección 2</a>.
            </p>
          </>
        ),
      },
      {
        id: 'intellectual-property',
        title: 'Propiedad intelectual',
        body: (
          <>
            <p>
              Los textos, el diseño, los logotipos y los demás contenidos de este sitio pertenecen a {name} o se usan con
              autorización. Puedes leerlos y compartir enlaces a ellos, pero no copiarlos, modificarlos ni usarlos con fines
              comerciales sin nuestra autorización escrita.
            </p>
            <p>
              El software que hace funcionar el sitio y el portal es propietario y está protegido por derechos de autor. No
              está permitido copiarlo, modificarlo ni intentar extraer su código fuente. Los cuestionarios estandarizados
              que usamos pertenecen a sus respectivos autores.
            </p>
          </>
        ),
      },
      {
        id: 'liability',
        title: 'Límites de nuestra responsabilidad',
        body: (
          <>
            <p>
              Trabajamos para que el sitio y el portal estén disponibles y sean precisos y seguros, pero no podemos
              garantizar que funcionen siempre sin interrupciones ni errores, por ejemplo durante un mantenimiento o por
              fallas de los proveedores de internet. La información general de este sitio no reemplaza una consulta
              profesional.
            </p>
            <p>
              No respondemos por daños causados por el uso indebido de tu cuenta cuando no se deba a una falla nuestra, por
              hechos que estén fuera de nuestro control razonable ni por el contenido de sitios de terceros a los que
              enlacemos.
            </p>
            <p>
              Nada de lo que dicen estos términos limita los derechos que tienes como consumidor según la Ley 1480 de 2011
              (Estatuto del Consumidor), ni la responsabilidad que nos corresponde por la atención profesional que
              prestamos, en los términos de la ley colombiana.
            </p>
          </>
        ),
      },
      {
        id: 'changes-and-contact',
        title: 'Cambios, ley aplicable y contacto',
        body: (
          <>
            <p>
              Podemos actualizar estos términos cuando cambien nuestros servicios o la ley. La fecha que aparece al inicio
              indica la versión vigente. Si los cambios son importantes, te avisaremos antes de que apliquen, por ejemplo por
              correo electrónico.
            </p>
            <p>
              Estos términos se rigen por las leyes de la República de Colombia. Si surge un desacuerdo, nos gustaría
              intentar resolverlo primero directamente contigo. Si no es posible, puedes acudir a las autoridades
              colombianas competentes.
            </p>
            <p>
              Estos términos están disponibles en español y en inglés. Si encuentras alguna diferencia entre las dos
              versiones, prevalece la versión en español.
            </p>
            <p>Si tienes preguntas sobre estos términos, escríbenos o llámanos:</p>
            <ContactDetails info={info} lang="es" />
          </>
        ),
      },
    ],
  };
}
