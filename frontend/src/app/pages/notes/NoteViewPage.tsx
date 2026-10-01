import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, PageHeader } from '@/components/ui/Display';
import { QueryState, useConfirm } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get, post } from '@/lib/api';
import { ageFrom, formatDate, formatDateTime, fullName } from '@/lib/format';
import { labelOf, RISK_TONE, useMeta } from '@/lib/meta';
import type { Note } from '@/lib/types';
import { useAction } from '../../useAction';

interface NoteDetail {
  note: Note;
  lockHours: number;
  canSign: boolean;
  clinic: { name: string; address: string; phone: string };
}

const LABELS: Record<string, [string, keyof Note][]> = {
  soap: [
    ['Subjetivo', 'subjective'],
    ['Objetivo', 'objective'],
    ['Análisis', 'assessment'],
    ['Plan', 'plan'],
  ],
  dap: [
    ['Datos', 'subjective'],
    ['Análisis', 'assessment'],
    ['Plan', 'plan'],
  ],
  free: [['Nota de sesión', 'subjective']],
};

export default function NoteViewPage() {
  const { id = '' } = useParams();
  const { data: meta } = useMeta();
  const confirm = useConfirm();
  const query = useQuery({ queryKey: ['note', id], queryFn: () => get<NoteDetail>(`/api/notes/${id}`) });
  const detail = query.data;
  const note = detail?.note;
  useDocumentTitle(note ? `Sesión ${note.session_number} · ${fullName(note)}` : 'Nota de sesión');

  const sign = useAction(() => post(`/api/notes/${id}/sign`), { invalidate: [['note', id], ['notes'], ['dashboard']] });

  const onSign = async () => {
    const ok = await confirm({
      title: 'Firmar nota',
      text: 'Al firmarla, la nota queda bloqueada de forma definitiva y ya no podrá editarse. Revisa que el contenido esté completo.',
      confirmLabel: 'Firmar y bloquear',
    });
    if (ok) await sign.run(undefined).catch(() => undefined);
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && note && (
        <>
          <PageHeader
            back={{ to: `/app/pacientes/${note.patient_id}?tab=notas`, label: fullName(note) }}
            title={`Sesión ${note.session_number}`}
            subtitle={`${formatDate(note.session_date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · ${note.author_name}`}
            actions={
              <>
                <Button icon="print" onClick={() => window.print()}>
                  Imprimir
                </Button>
                {!note.is_locked && (
                  <ButtonLink to={`/app/notas/${note.id}/editar`} icon="edit">
                    Editar
                  </ButtonLink>
                )}
                {detail.canSign && (
                  <Button variant="primary" icon="pen" onClick={onSign} loading={sign.pending}>
                    Firmar nota
                  </Button>
                )}
              </>
            }
          />

          {!note.is_locked && (
            <div className="risk-banner no-print">
              <Alert tone="warning" title="Borrador sin firmar">
                Puede editarse hasta que la firmes. La política de la clínica sugiere firmar dentro de las {detail.lockHours} horas
                siguientes a la sesión.
              </Alert>
            </div>
          )}

          <article className="document-sheet">
            <header className="document-sheet__head">
              <div>
                <p className="serif document-sheet__clinic">
                  {detail.clinic.name}
                </p>
                <p className="xsmall muted">
                  {[detail.clinic.address, detail.clinic.phone].filter(Boolean).join(' · ')}
                </p>
              </div>
              <div className="cluster cluster--end">
                <Badge>{labelOf(meta?.noteFormats, note.format).split(' (')[0]}</Badge>
                <Badge tone={RISK_TONE[note.risk_level]}>Riesgo {labelOf(meta?.riskLevels, note.risk_level).toLowerCase()}</Badge>
                {note.is_locked ? <Badge tone="success">Firmada</Badge> : <Badge tone="warning">Borrador</Badge>}
              </div>
            </header>

            <dl className="facts document-sheet__facts">
              <div>
                <dt>Paciente</dt>
                <dd>
                  <Link to={`/app/pacientes/${note.patient_id}`}>{fullName(note)}</Link>
                </dd>
              </div>
              <div>
                <dt>Historia</dt>
                <dd>{note.record_number}</dd>
              </div>
              <div>
                <dt>Edad</dt>
                <dd>{ageFrom(note.birth_date) ?? '—'}</dd>
              </div>
              <div>
                <dt>Ánimo percibido</dt>
                <dd>{note.mood_score !== null ? `${note.mood_score} / 10` : '—'}</dd>
              </div>
            </dl>

            {LABELS[note.format]?.map(([label, key]) => (
              <section key={key} className="prose-block">
                <h3>{label}</h3>
                <p>{(note[key] as string | null) || '—'}</p>
              </section>
            ))}

            {note.interventions && (
              <section className="prose-block">
                <h3>Intervenciones</h3>
                <p>{note.interventions}</p>
              </section>
            )}
            {note.homework && (
              <section className="prose-block">
                <h3>Tareas acordadas</h3>
                <p>{note.homework}</p>
              </section>
            )}

            <footer className="document-sheet__signature">
              <p>
                <strong>{note.author_name}</strong>
              </p>
              <p className="muted">Registro profesional {note.license_number || '—'}</p>
              {note.is_locked && <p className="muted">Firmada electrónicamente el {formatDateTime(note.locked_at)}</p>}
            </footer>
          </article>
        </>
      )}
    </QueryState>
  );
}
