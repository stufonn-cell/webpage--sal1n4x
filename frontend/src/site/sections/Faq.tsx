import { Icon } from '@/components/ui/Icon';
import type { Faq as FaqItem } from '../content';

/**
 * Frequently asked questions with <details>: works without JavaScript, with a
 * keyboard and with screen readers. The name attribute opens one at a time.
 */
export function Faq({ items }: { items: FaqItem[] }) {
  return (
    <section className="section" id="faq" aria-labelledby="faq-title">
      <div className="container faq">
        <header className="section__head reveal">
          <p className="eyebrow">Frequently asked questions</p>
          <h2 className="section__title serif" id="faq-title">
            What people often ask us before starting
          </h2>
          <p className="section__lead">If your question is not here, write to us. No question is too small.</p>
        </header>

        <div className="faq__list reveal">
          {items.map((item) => (
            <details key={item.question} className="accordion" name="faq">
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
