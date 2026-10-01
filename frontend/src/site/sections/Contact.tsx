import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { PublicSite } from '@/lib/types';
import { telLink, whatsappLink } from '../useSite';

export function Contact({ site }: { site?: PublicSite }) {
  const clinic = site?.clinic;

  return (
    <section className="section section--tinted" id="contact" aria-labelledby="contact-title">
      <div className="container contact">
        <div className="contact__intro reveal">
          <p className="eyebrow">Contact</p>
          <h2 className="section__title serif" id="contact-title">
            We are here whenever you are ready
          </h2>
          <p className="section__lead">
            The easiest way to start is the online request. If you would rather talk to someone, you can also call us or
            write to us.
          </p>
          <ButtonLink to="/request-appointment" variant="primary" size="lg" iconRight="arrowRight">
            Request an appointment
          </ButtonLink>
        </div>

        <ul className="contact__list reveal">
          {clinic?.clinic_phone && (
            <li>
              <Icon name="phone" size={18} />
              <div>
                <span className="contact__label">Phone</span>
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
                  Message us on WhatsApp
                </a>
              </div>
            </li>
          )}
          {clinic?.clinic_email && (
            <li>
              <Icon name="mail" size={18} />
              <div>
                <span className="contact__label">Email</span>
                <a href={`mailto:${clinic.clinic_email}`}>{clinic.clinic_email}</a>
              </div>
            </li>
          )}
          {clinic?.clinic_address && (
            <li>
              <Icon name="mapPin" size={18} />
              <div>
                <span className="contact__label">Office</span>
                <span>{clinic.clinic_address}</span>
              </div>
            </li>
          )}
          {clinic?.working_hours_start && clinic.working_hours_end && (
            <li>
              <Icon name="clock" size={18} />
              <div>
                <span className="contact__label">Opening hours</span>
                <span>
                  From {clinic.working_hours_start} to {clinic.working_hours_end}
                </span>
              </div>
            </li>
          )}
        </ul>
      </div>
    </section>
  );
}
