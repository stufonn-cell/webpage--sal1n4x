import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, PageHeader } from '@/components/ui/Display';
import { QueryState, useConfirm } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get, post } from '@/lib/api';
import { documentText, translateInterventions } from '@/lib/documentText';
import { ageFrom, formatDate, formatDateTime, fullName } from '@/lib/format';
import { RISK_TONE } from '@/lib/meta';
import type { Note } from '@/lib/types';
import { DocumentLanguageSelect, useDocumentLanguage } from '../../components/DocumentLanguageSelect';
import { useAction } from '../../useAction';

interface NoteDetail {
  note: Note;
  lockHours: number;
  canSign: boolean;
  clinic: { name: string; address: string; phone: string };
}

type Section = keyof ReturnType<typeof documentText>['note']['sections'];

const SECTIONS: Record<string, [Section, keyof Note][]> = {
  soap: [
    ['subjective', 'subjective'],
    ['objective', 'objective'],
    ['assessment', 'assessment'],
    ['plan', 'plan'],
  ],
  dap: [
    ['data', 'subjective'],
    ['assessment', 'assessment'],
    ['plan', 'plan'],
  ],
  free: [['free', 'subjective']],
};

export default function NoteViewPage() {
  const { id = '' } = useParams();
  const confirm = useConfirm();
  const [language, setLanguage] = useDocumentLanguage();
  const t = documentText(language);
  const query = useQuery({ queryKey: ['note', id], queryFn: () => get<NoteDetail>(`/api/notes/${id}`) });
  const detail = query.data;
  const note = detail?.note;
  useDocumentTitle(note ? `Session ${note.session_number} · ${fullName(note)}` : 'Session note');

  const sign = useAction(() => post(`/api/notes/${id}/sign`), { invalidate: [['note', id], ['notes'], ['dashboard']] });

  const onSign = async () => {
    const ok = await confirm({
      title: 'Sign note',
      text: "Once you sign it, the note is locked for good and can't be edited anymore. Make sure everything is complete.",
      confirmLabel: 'Sign and lock',
    });
    if (ok) await sign.run(undefined).catch(() => undefined);
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && note && (
        <>
          <PageHeader
            back={{ to: `/app/patients/${note.patient_id}?tab=notes`, label: fullName(note) }}
            title={`Session ${note.session_number}`}
            subtitle={`${formatDate(note.session_date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · ${note.author_name}`}
            actions={
              <>
                <DocumentLanguageSelect value={language} onChange={setLanguage} />
                <Button icon="print" onClick={() => window.print()}>
                  Print
                </Button>
                {!note.is_locked && (
                  <ButtonLink to={`/app/notes/${note.id}/edit`} icon="edit">
                    Edit
                  </ButtonLink>
                )}
                {detail.canSign && (
                  <Button variant="primary" icon="pen" onClick={onSign} loading={sign.pending}>
                    Sign note
                  </Button>
                )}
              </>
            }
          />

          {!note.is_locked && (
            <div className="risk-banner no-print">
              <Alert tone="warning" title="Unsigned draft">
                You can edit it until you sign it. Clinic policy suggests signing within {detail.lockHours} hours
                of the session.
              </Alert>
            </div>
          )}

          <article className="document-sheet" lang={language}>
            <header className="document-sheet__head">
              <div>
                <p className="serif document-sheet__clinic">{detail.clinic.name}</p>
                <p className="xsmall muted">{[detail.clinic.address, detail.clinic.phone].filter(Boolean).join(' · ')}</p>
                <p className="document-sheet__title">
                  {t.note.title(note.session_number)} · {formatDate(note.session_date, { day: 'numeric', month: 'long', year: 'numeric' }, t.locale)}
                </p>
              </div>
              <div className="cluster cluster--end">
                <Badge>{t.note.formats[note.format] ?? note.format}</Badge>
                <Badge tone={RISK_TONE[note.risk_level]}>
                  {t.note.risk}: {t.note.riskLevels[note.risk_level] ?? note.risk_level}
                </Badge>
                {note.is_locked ? <Badge tone="success">{t.note.signed}</Badge> : <Badge tone="warning">{t.note.draft}</Badge>}
              </div>
            </header>

            <dl className="facts document-sheet__facts">
              <div>
                <dt>{t.patient}</dt>
                <dd>
                  <Link to={`/app/patients/${note.patient_id}`}>{fullName(note)}</Link>
                </dd>
              </div>
              <div>
                <dt>{t.recordNumber}</dt>
                <dd>{note.record_number}</dd>
              </div>
              <div>
                <dt>{t.age}</dt>
                <dd>{ageFrom(note.birth_date) !== null ? t.yearsOld(ageFrom(note.birth_date) as number) : '—'}</dd>
              </div>
              <div>
                <dt>{t.note.mood}</dt>
                <dd>{note.mood_score !== null ? `${note.mood_score} / 10` : '—'}</dd>
              </div>
            </dl>

            {SECTIONS[note.format]?.map(([section, key]) => (
              <section key={section} className="prose-block">
                <h3>{t.note.sections[section]}</h3>
                <p>{(note[key] as string | null) || '—'}</p>
              </section>
            ))}

            {note.interventions && (
              <section className="prose-block">
                <h3>{t.note.interventions}</h3>
                <p>{translateInterventions(note.interventions, language)}</p>
              </section>
            )}
            {note.homework && (
              <section className="prose-block">
                <h3>{t.note.homework}</h3>
                <p>{note.homework}</p>
              </section>
            )}

            <footer className="document-sheet__signature">
              <p>
                <strong>{note.author_name}</strong>
              </p>
              <p className="muted">
                {t.professionalLicense} {note.license_number || '—'}
              </p>
              {note.is_locked && <p className="muted">{t.note.signedOn(formatDateTime(note.locked_at, t.locale))}</p>}
            </footer>
          </article>
        </>
      )}
    </QueryState>
  );
}
