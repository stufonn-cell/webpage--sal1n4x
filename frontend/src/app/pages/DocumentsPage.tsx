import { useQuery } from '@tanstack/react-query';
import { PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import type { DocumentRow } from '@/lib/types';
import { DocumentTable } from '../components/DocumentTable';
import { DocumentUpload } from '../components/DocumentUpload';

export default function DocumentsPage() {
  useDocumentTitle('Documents');
  const query = useQuery({ queryKey: ['documents'], queryFn: () => get<DocumentRow[]>('/api/documents') });

  return (
    <>
      <PageHeader title="Documents" subtitle="Your library of reports, referrals and supporting files. Every download is recorded in the audit log." />
      <div className="layout-aside">
        <Panel flush title="Library" titleId="library">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            <DocumentTable documents={query.data ?? []} showPatient />
          </QueryState>
        </Panel>
        <Panel title="Upload a document" titleId="upload">
          <DocumentUpload />
        </Panel>
      </div>
    </>
  );
}
