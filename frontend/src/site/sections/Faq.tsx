import { Icon } from '@/components/ui/Icon';
import type { Faq as FaqItem } from '../content';

/**
 * Preguntas frecuentes con <details>: funciona sin JavaScript, con teclado y
 * con lectores de pantalla. El atributo name hace que se abra una a la vez.
 */
export function Faq({ items }: { items: FaqItem[] }) {
  return (
    <section className="section" id="preguntas" aria-labelledby="preguntas-title">
      <div className="container faq">
        <header className="section__head reveal">
          <p className="eyebrow">Preguntas frecuentes</p>
          <h2 className="section__title serif" id="preguntas-title">
            Lo que suelen preguntarnos antes de empezar
          </h2>
          <p className="section__lead">Si tu duda no está aquí, escríbenos. Ninguna pregunta está de más.</p>
        </header>

        <div className="faq__list reveal">
          {items.map((item) => (
            <details key={item.question} className="accordion" name="preguntas">
              <summary>
                <span className="accordion__label">{item.question}</span>
                <Icon name="plus" size={18} className="accordion__chevron accordion__chevron--plus" />
              </summary>
              <div className="accordion__body">
                <p>{item.answer}</p>
              </div>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}
