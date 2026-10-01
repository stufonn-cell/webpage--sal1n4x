import { Link } from 'react-router';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/Display';
import { useConfirm } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { del } from '@/lib/api';
import { formatBytes, formatDate, fullName } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { DocumentRow } from '@/lib/types';
import { useAction } from '../useAction';

export function DocumentTable({ documents, showPatient = false }: { documents: DocumentRow[]; showPatient?: boolean }) {
  const { data: meta } = useMeta();
  const confirm = useConfirm();
  const remove = useAction((id: number) => del(`/api/documents/${id}`), { invalidate: [['documents'], ['patient']] });

  if (documents.length === 0) {
    return <EmptyState icon="file" title="No documents yet" text="Reports, referrals and supporting files you attach will show up here." />;
  }

  const onDelete = async (document: DocumentRow) => {
    const ok = await confirm({
      title: 'Delete document',
      text: `“${document.title}” will be permanently deleted. This can't be undone.`,
      confirmLabel: 'Delete',
      danger: true,
    });
    if (ok) await remove.run(document.id).catch(() => undefined);
  };

  return (
    <div className="table-wrap">
      <table className="table table--stack">
        <thead>
          <tr>
            <th scope="col">Document</th>
            {showPatient && <th scope="col">Patient</th>}
            <th scope="col">Category</th>
            <th scope="col">Date</th>
            <th scope="col">
              <span className="visually-hidden">Actions</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {documents.map((document) => (
            <tr key={document.id}>
              <td>
                <span className="table__link">{document.title}</span>
                <span className="table__sub">
                  {document.original_name} · {formatBytes(document.size_bytes)}
                </span>
              </td>
              {showPatient && (
                <td data-label="Patient">
                  <Link to={`/app/patients/${document.patient_id}`}>{fullName(document)}</Link>
                </td>
              )}
              <td data-label="Category">{labelOf(meta?.documentCategories, document.category)}</td>
              <td data-label="Date">
                {formatDate(document.created_at)}
                {document.uploaded_by_name && <span className="table__sub">{document.uploaded_by_name}</span>}
              </td>
              <td className="num">
                <div className="cluster cluster--end cluster--nowrap">
                  <a className="btn btn--sm btn--quiet" href={`/api/documents/${document.id}/download`} aria-label={`Download ${document.title}`}>
                    <Icon name="download" size={15} />
                    <span className="btn__label">Download</span>
                  </a>
                  <Button size="sm" variant="quiet" iconOnly icon="trash" aria-label={`Delete ${document.title}`} onClick={() => onDelete(document)} />
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
