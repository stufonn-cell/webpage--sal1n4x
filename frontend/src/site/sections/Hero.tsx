import { ButtonLink } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import type { PublicSite } from '@/lib/types';
import { enterDelay } from '@/lib/style';
import { DEFAULT_ABOUT } from '../content';

/**
 * Ilustracion de linea: dos sillones enfrentados junto a una ventana. Evoca
 * una conversacion sin recurrir a fotos de stock genericas.
 */
function ConversationIllustration() {
  return (
    <svg className="hero__art" viewBox="0 0 480 420" role="img" aria-label="Dos sillones enfrentados junto a una ventana, listos para conversar">
      <path
        className="hero__blob"
        d="M84 92c46-56 142-78 222-56 82 22 150 86 156 170 6 86-52 170-142 196-92 26-206 8-262-56C2 282 6 186 84 92Z"
      />
      <g className="hero__line">
        <path d="M240 64h120v128H240ZM300 64v128" />
        <path d="M240 128h120" />
        <path d="M104 318V246a26 26 0 0 1 26-26h46a26 26 0 0 1 26 26v24" />
        <path d="M88 278h126a14 14 0 0 1 14 14v26H74v-26a14 14 0 0 1 14-14Z" />
        <path d="M86 318v26M216 318v26" />
        <path d="M376 318V246a26 26 0 0 0-26-26h-46a26 26 0 0 0-26 26v24" />
        <path d="M392 278H266a14 14 0 0 0-14 14v26h154v-26a14 14 0 0 0-14-14Z" />
        <path d="M394 318v26M264 318v26" />
        <path d="M240 318v-56M224 262h32l-4 56h-24l-4-56Z" />
        <path className="hero__leaf" d="M240 262c-2-22-16-34-34-38 4 18 16 32 34 38ZM240 250c4-22 18-32 36-34-4 18-18 30-36 34Z" />
        <path d="M60 344h360" />
      </g>
    </svg>
  );
}

export function Hero({ site }: { site?: PublicSite }) {
  const clinic = site?.clinic;
  const minutes = Number(clinic?.session_duration) > 0 ? clinic?.session_duration : '50';

  return (
    <section className="hero" aria-labelledby="hero-title">
      <div className="container hero__grid">
        <div className="hero__copy">
          <p className="eyebrow enter">Atención psicológica · presencial y virtual</p>
          <h1 className="hero__title serif enter" id="hero-title" style={enterDelay(60)}>
            Un lugar tranquilo para hablar de lo que <em>te pesa</em>.
          </h1>
          <p className="hero__lead enter" style={enterDelay(140)}>
            {clinic?.clinic_about || DEFAULT_ABOUT}
          </p>
          <div className="hero__actions enter" style={enterDelay(220)}>
            <ButtonLink to="/solicitar-cita" variant="primary" size="lg" iconRight="arrowRight">
              Solicitar una primera cita
            </ButtonLink>
            <a className="hero__secondary" href="#proceso">
              ¿Cómo es el primer paso?
            </a>
          </div>
          <ul className="hero__facts enter" style={enterDelay(300)}>
            <li>
              <Icon name="clock" size={16} />
              Sesiones de {minutes} minutos
            </li>
            <li>
              <Icon name="lock" size={16} />
              Confidencialidad profesional
            </li>
            <li>
              <Icon name="video" size={16} />
              En consultorio o en línea
            </li>
          </ul>
        </div>
        <div className="hero__visual enter" style={enterDelay(120)}>
          <ConversationIllustration />
        </div>
      </div>
    </section>
  );
}
