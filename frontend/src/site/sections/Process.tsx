import { ButtonLink } from '@/components/ui/Button';
import { revealDelay } from '@/lib/style';
import { STEPS } from '../content';

export function Process() {
  return (
    <section className="section section--tinted" id="getting-started" aria-labelledby="getting-started-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Getting started</p>
          <h2 className="section__title serif" id="getting-started-title">
            Taking the first step can be simple
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
          <ButtonLink to="/request-appointment" variant="primary" size="lg">
            Get started now
          </ButtonLink>
          <p className="muted small">It takes about two minutes. Writing to us does not commit you to anything.</p>
        </div>
      </div>
    </section>
  );
}
