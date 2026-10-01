import { useQuery } from '@tanstack/react-query';
import { PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import type { DocumentRow } from '@/lib/types';
import { DocumentTable } from '../components/DocumentTable';
import { DocumentUpload } from '../components/DocumentUpload';

export default function DocumentsPage() {
  useDocumentTitle('Documentos');
  const query = useQuery({ queryKey: ['documents'], queryFn: () => get<DocumentRow[]>('/api/documents') });

  return (
    <>
      <PageHeader title="Documentos" subtitle="Repositorio de informes, remisiones y soportes. Cada descarga queda registrada en la auditoría." />
      <div className="layout-aside">
        <Panel flush title="Repositorio" titleId="repositorio">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            <DocumentTable documents={query.data ?? []} showPatient />
          </QueryState>
        </Panel>
        <Panel title="Subir documento" titleId="subir">
          <DocumentUpload />
        </Panel>
      </div>
    </>
  );
}
