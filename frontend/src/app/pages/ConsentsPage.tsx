import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { ConsentDocument } from '@/components/forms/ConsentDocument';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, fullName } from '@/lib/format';
import type { Consent } from '@/lib/types';
import { ConsentCreate } from '../components/ConsentCreate';

export function ConsentListPage() {
  useDocumentTitle('Consentimientos');
  const query = useQuery({ queryKey: ['consents'], queryFn: () => get<Consent[]>('/api/consents') });

  return (
    <>
      <PageHeader title="Consentimientos informados" subtitle="Generados desde las plantillas de la clínica y firmados con trazo." />
      <div className="layout-aside">
        <Panel flush title="Documentos generados" titleId="generados">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            {query.data?.length === 0 ? (
              <EmptyState icon="clipboard" title="Aún no hay consentimientos" />
            ) : (
              <ul className="list">
                {query.data?.map((consent) => (
                  <li key={consent.id}>
                    <Link className="list__item" to={`/app/consentimientos/${consent.id}`}>
                      <span className="list__main">
                        <span className="list__title">{consent.title}</span>
                        <span className="list__meta">
                          {fullName(consent)} · {consent.status === 'signed' ? `firmado el ${formatDate(consent.signed_at)}` : `generado el ${formatDate(consent.created_at)}`}
                        </span>
                      </span>
                      <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>{consent.status === 'signed' ? 'Firmado' : 'Pendiente'}</Badge>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </QueryState>
        </Panel>
        <Panel title="Generar consentimiento" titleId="nuevo">
          <ConsentCreate />
        </Panel>
      </div>
    </>
  );
}

export function ConsentViewPage() {
  const { id = '' } = useParams();
  const query = useQuery({ queryKey: ['consent', id], queryFn: () => get<Consent>(`/api/consents/${id}`) });
  useDocumentTitle(query.data?.title ?? 'Consentimiento');

  return (
    <>
      <PageHeader
        back={query.data ? { to: `/app/pacientes/${query.data.patient_id}?tab=administrativo`, label: fullName(query.data) } : { to: '/app/consentimientos', label: 'Consentimientos' }}
        title={query.data?.title ?? 'Consentimiento'}
      />
      <div className="container--narrow">
        <ConsentDocument id={id} audience="staff" />
      </div>
    </>
  );
}
