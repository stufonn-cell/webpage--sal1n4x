import { useId, useState, type KeyboardEvent } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { PublicSite } from '@/lib/types';
import { SERVICES, type Service } from '../content';

function ServiceDetail({ service, site }: { service: Service; site?: PublicSite }) {
  return (
    <div className="service-detail">
      <p className="service-detail__modality">
        <Icon name={service.id === 'virtual' ? 'video' : 'mapPin'} size={15} />
        {service.modality}
      </p>
      <h3 className="service-detail__title serif">{service.title}</h3>
      <p className="service-detail__for">{service.forWhom}</p>
      <p className="eyebrow">Cómo trabajamos</p>
      <ol className="service-detail__steps">
        {service.how.map((step) => (
          <li key={step}>{step}</li>
        ))}
      </ol>
      {service.id === 'evaluacion' && site && site.instruments.length > 0 && (
        <ul className="service-detail__tags" aria-label="Instrumentos disponibles">
          {site.instruments.map((instrument) => (
            <li key={instrument.code} title={instrument.name}>
              {instrument.code} · {instrument.domain}
            </li>
          ))}
        </ul>
      )}
      <ButtonLink to={`/solicitar-cita?servicio=${service.id}`} variant="default" iconRight="arrowRight">
        Pedir una cita para esto
      </ButtonLink>
    </div>
  );
}

/**
 * Explorador de servicios. En escritorio: lista a la izquierda y detalle a la
 * derecha (patron de pestanas verticales). En movil: acordeon.
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
    <section className="section" id="servicios" aria-labelledby="servicios-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Servicios</p>
          <h2 className="section__title serif" id="servicios-title">
            Acompañamiento para distintos momentos de la vida
          </h2>
          <p className="section__lead">
            Cada proceso es distinto. Estas son las formas en que podemos acompañarte; si no sabes cuál elegir, lo
            conversamos en el primer contacto.
          </p>
        </header>

        <div className="services reveal">
          <div className="services__list" role="tablist" aria-orientation="vertical" aria-label="Servicios">
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
            <details key={service.id} className="accordion" name="servicios">
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
            <p className="eyebrow">Enfoques con los que trabajamos</p>
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
