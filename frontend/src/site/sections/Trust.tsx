import { Link } from 'react-router';
import { Icon } from '@/components/ui/Icon';
import { revealDelay } from '@/lib/style';
import { PRIVACY_POINTS } from '../content';

export function Trust() {
  return (
    <section className="section section--ink" aria-labelledby="confianza-title">
      <div className="container trust">
        <header className="trust__head reveal">
          <p className="eyebrow">Confidencialidad</p>
          <h2 className="section__title serif" id="confianza-title">
            Lo que compartes aquí se queda aquí
          </h2>
          <Link className="trust__link" to="/privacidad">
            Cómo cuidamos tu información <Icon name="arrowRight" size={16} />
          </Link>
        </header>
        <ul className="trust__points">
          {PRIVACY_POINTS.map((point, index) => (
            <li key={point.title} className="reveal" style={revealDelay(index * 90)}>
              <Icon name={point.icon} size={20} />
              <h3>{point.title}</h3>
              <p>{point.text}</p>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
