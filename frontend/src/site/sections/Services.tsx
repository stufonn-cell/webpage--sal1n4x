import { useId, useState, type KeyboardEvent } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { PublicSite } from '@/lib/types';
import { SERVICES, type Service } from '../content';

function ServiceDetail({ service, site }: { service: Service; site?: PublicSite }) {
  return (
    <div className="service-detail">
      <p className="service-detail__modality">
        <Icon name={service.id === 'online' ? 'video' : 'mapPin'} size={15} />
        {service.modality}
      </p>
      <h3 className="service-detail__title serif">{service.title}</h3>
      <p className="service-detail__for">{service.forWhom}</p>
      <p className="eyebrow">How we work</p>
      <ol className="service-detail__steps">
        {service.how.map((step) => (
          <li key={step}>{step}</li>
        ))}
      </ol>
      {service.id === 'assessment' && site && site.instruments.length > 0 && (
        <ul className="service-detail__tags" aria-label="Available instruments">
          {site.instruments.map((instrument) => (
            <li key={instrument.code} title={instrument.name}>
              {instrument.code} · {instrument.domain}
            </li>
          ))}
        </ul>
      )}
      <ButtonLink to={`/request-appointment?service=${service.id}`} variant="default" iconRight="arrowRight">
        Book an appointment for this
      </ButtonLink>
    </div>
  );
}

/**
 * Service explorer. On desktop: list on the left and details on the right
 * (vertical tabs pattern). On mobile: accordion.
 */
export function Services({ site }: { site?: PublicSite }) {
  const [active, setActive] = useState(SERVICES[0].id);
  const baseId = useId();
  const current = SERVICES.find((service) => service.id === active) ?? SERVICES[0];

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    const delta = event.key === 'ArrowDown' ? 1 : event.key === 'ArrowUp' ? -1 : 0;
    if (!delta) return;
    event.preventDefault();
    const next = SERVICES[(index + delta + SERVICES.length) % SERVICES.length];
    setActive(next.id);
    document.getElementById(`${baseId}-${next.id}`)?.focus();
  };

  return (
    <section className="section" id="services" aria-labelledby="services-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Services</p>
          <h2 className="section__title serif" id="services-title">
            Support for different moments in life
          </h2>
          <p className="section__lead">
            Every process is different. These are the ways we can support you; if you are not sure which one to choose,
            we can talk it through when we first get in touch.
          </p>
        </header>

        <div className="services reveal">
          <div className="services__list" role="tablist" aria-orientation="vertical" aria-label="Services">
            {SERVICES.map((service, index) => (
              <button
                key={service.id}
                id={`${baseId}-${service.id}`}
                className="services__item"
                role="tab"
                type="button"
                aria-selected={service.id === active}
                aria-controls={`${baseId}-panel`}
                tabIndex={service.id === active ? 0 : -1}
                onClick={() => setActive(service.id)}
                onKeyDown={(event) => onKeyDown(event, index)}
              >
                <span className="services__icon">
                  <Icon name={service.icon} size={18} />
                </span>
                <span>
                  <span className="services__name">{service.title}</span>
                  <span className="services__short">{service.short}</span>
                </span>
              </button>
            ))}
          </div>
          <div
            className="services__panel"
            id={`${baseId}-panel`}
            role="tabpanel"
            aria-labelledby={`${baseId}-${current.id}`}
            key={current.id}
          >
            <ServiceDetail service={current} site={site} />
          </div>
        </div>

        <div className="services-accordion reveal">
          {SERVICES.map((service) => (
            <details key={service.id} className="accordion" name="services">
              <summary>
                <span className="services__icon">
                  <Icon name={service.icon} size={18} />
                </span>
                <span className="accordion__label">
                  <span className="services__name">{service.title}</span>
                  <span className="services__short">{service.short}</span>
                </span>
                <Icon name="chevronDown" size={18} className="accordion__chevron" />
              </summary>
              <div className="accordion__body">
                <ServiceDetail service={service} site={site} />
              </div>
            </details>
          ))}
        </div>

        {site && site.approaches.length > 0 && (
          <div className="approaches reveal">
            <p className="eyebrow">Approaches we work with</p>
            <ul className="approaches__list">
              {site.approaches.map((approach) => (
                <li key={approach}>{approach}</li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </section>
  );
}
