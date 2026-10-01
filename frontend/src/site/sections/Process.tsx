import { ButtonLink } from '@/components/ui/Button';
import { revealDelay } from '@/lib/style';
import { STEPS } from '../content';

export function Process() {
  return (
    <section className="section section--tinted" id="proceso" aria-labelledby="proceso-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Cómo empezar</p>
          <h2 className="section__title serif" id="proceso-title">
            Dar el primer paso puede ser sencillo
          </h2>
        </header>

        <ol className="steps">
          {STEPS.map((step, index) => (
            <li key={step.title} className="steps__item reveal" style={revealDelay(index * 90)}>
              <span className="steps__number serif" aria-hidden="true">
                {index + 1}
              </span>
              <h3 className="steps__title">{step.title}</h3>
              <p className="steps__text">{step.text}</p>
            </li>
          ))}
        </ol>

        <div className="section__cta reveal">
          <ButtonLink to="/solicitar-cita" variant="primary" size="lg">
            Empezar ahora
          </ButtonLink>
          <p className="muted small">Toma unos dos minutos. Puedes escribirnos sin compromiso.</p>
        </div>
      </div>
    </section>
  );
}
