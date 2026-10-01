import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { TextField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { useDebounced } from '@/hooks/useDebounced';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, fullName } from '@/lib/format';
import { labelOf, RISK_TONE, useMeta } from '@/lib/meta';
import type { Note, Page } from '@/lib/types';

export default function NoteListPage() {
  const { data: meta } = useMeta();
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const search = useDebounced(q, 300);
  useDocumentTitle('Session notes');

  const query = useQuery({
    queryKey: ['notes', search, page],
    queryFn: () => get<Page<Note>>('/api/notes', { q: search, page }),
    placeholderData: keepPreviousData,
  });

  return (
    <>
      <PageHeader
        title="Session notes"
        subtitle="Signed notes are locked and can't be changed."
        actions={
          <ButtonLink to="/app/notes/new" variant="primary" icon="plus">
            New note
          </ButtonLink>
        }
      />
      <div className="toolbar" role="search">
        <TextField
          wrapperClassName="toolbar__search"
          label="Search notes"
          id="note-search"
          type="search"
          placeholder="Patient or content"
          value={q}
          onChange={(event) => {
            setQ(event.target.value);
            setPage(1);
          }}
        />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState icon="note" title={q ? 'No notes match your search' : 'No notes yet'} />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Session</th>
                    <th scope="col">Format</th>
                    <th scope="col">Risk</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((note) => (
                    <tr key={note.id}>
                      <td>
                        <Link className="table__link" to={`/app/notes/${note.id}`}>
                          {fullName(note)}
                        </Link>
                        <span className="table__sub">{note.author_name}</span>
                      </td>
                      <td data-label="Session">
                        #{note.session_number} · {formatDate(note.session_date)}
                      </td>
                      <td data-label="Format">{note.format.toUpperCase()}</td>
                      <td data-label="Risk">
                        <Badge tone={RISK_TONE[note.risk_level]}>{labelOf(meta?.riskLevels, note.risk_level)}</Badge>
                      </td>
                      <td data-label="Status">
                        {note.is_locked ? <Badge tone="success">Signed</Badge> : <Badge tone="warning">Draft</Badge>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {query.data && <Pagination page={query.data.page} pages={query.data.pages} total={query.data.total} onChange={setPage} />}
        </QueryState>
      </Panel>
    </>
  );
}
