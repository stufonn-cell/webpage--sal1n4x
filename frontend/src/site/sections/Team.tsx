import { Avatar } from '@/components/ui/Display';
import type { PublicProfessional } from '@/lib/types';
import { revealDelay } from '@/lib/style';

/**
 * Equipo. Solo aparecen profesionales que activaron su perfil publico desde
 * la administracion; si no hay ninguno la seccion no se muestra.
 */
export function Team({ professionals }: { professionals: PublicProfessional[] }) {
  if (professionals.length === 0) return null;

  return (
    <section className="section" id="equipo" aria-labelledby="equipo-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Equipo</p>
          <h2 className="section__title serif" id="equipo-title">
            Quiénes te acompañan
          </h2>
          <p className="section__lead">
            Cada profesional aparece con su número de registro profesional, para que puedas verificarlo antes de tu
            primera sesión.
          </p>
        </header>

        <ul className="team">
          {professionals.map((person, index) => (
            <li key={person.id} className="team__person reveal" style={revealDelay(index * 80)}>
              <Avatar name={person.full_name} size="lg" />
              <div>
                <h3 className="team__name serif">{person.full_name}</h3>
                {person.specialty && <p className="team__specialty">{person.specialty}</p>}
                {person.public_bio && <p className="team__bio">{person.public_bio}</p>}
                {person.license_number && (
                  <p className="team__license">Registro profesional {person.license_number}</p>
                )}
              </div>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
