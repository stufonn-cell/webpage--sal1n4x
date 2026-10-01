import { Avatar } from '@/components/ui/Display';
import type { PublicProfessional } from '@/lib/types';
import { revealDelay } from '@/lib/style';

/**
 * Team. Only professionals whose public profile was turned on from the admin
 * area appear here; if there are none, the section is not shown.
 */
export function Team({ professionals }: { professionals: PublicProfessional[] }) {
  if (professionals.length === 0) return null;

  return (
    <section className="section" id="team" aria-labelledby="team-title">
      <div className="container">
        <header className="section__head reveal">
          <p className="eyebrow">Team</p>
          <h2 className="section__title serif" id="team-title">
            Who will be with you
          </h2>
          <p className="section__lead">
            Each professional is listed with their professional license number, so you can verify it before your first
            session.
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
                  <p className="team__license">Professional license {person.license_number}</p>
                )}
              </div>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
