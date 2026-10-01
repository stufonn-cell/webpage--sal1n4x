import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { PublicSite } from '@/lib/types';
import { telLink, whatsappLink } from '../useSite';

export function Contact({ site }: { site?: PublicSite }) {
  const clinic = site?.clinic;

  return (
    <section className="section section--tinted" id="contacto" aria-labelledby="contacto-title">
      <div className="container contact">
        <div className="contact__intro reveal">
          <p className="eyebrow">Contacto</p>
          <h2 className="section__title serif" id="contacto-title">
            Cuando quieras, aquí estamos
          </h2>
          <p className="section__lead">
            La forma más sencilla de empezar es la solicitud en línea. Si prefieres hablar con alguien, también puedes
            llamarnos o escribirnos.
          </p>
          <ButtonLink to="/solicitar-cita" variant="primary" size="lg" iconRight="arrowRight">
            Solicitar una cita
          </ButtonLink>
        </div>

        <ul className="contact__list reveal">
          {clinic?.clinic_phone && (
            <li>
              <Icon name="phone" size={18} />
              <div>
                <span className="contact__label">Teléfono</span>
                <a href={telLink(clinic.clinic_phone)}>{clinic.clinic_phone}</a>
              </div>
            </li>
          )}
          {clinic?.whatsapp_number && (
            <li>
              <Icon name="message" size={18} />
              <div>
                <span className="contact__label">WhatsApp</span>
                <a href={whatsappLink(clinic.whatsapp_number)} target="_blank" rel="noopener noreferrer">
                  Escribir por WhatsApp
                </a>
              </div>
            </li>
          )}
          {clinic?.clinic_email && (
            <li>
              <Icon name="mail" size={18} />
              <div>
                <span className="contact__label">Correo</span>
                <a href={`mailto:${clinic.clinic_email}`}>{clinic.clinic_email}</a>
              </div>
            </li>
          )}
          {clinic?.clinic_address && (
            <li>
              <Icon name="mapPin" size={18} />
              <div>
                <span className="contact__label">Consultorio</span>
                <span>{clinic.clinic_address}</span>
              </div>
            </li>
          )}
          {clinic?.working_hours_start && clinic.working_hours_end && (
            <li>
              <Icon name="clock" size={18} />
              <div>
                <span className="contact__label">Horario de atención</span>
                <span>
                  De {clinic.working_hours_start} a {clinic.working_hours_end}
                </span>
              </div>
            </li>
          )}
        </ul>
      </div>
    </section>
  );
}
