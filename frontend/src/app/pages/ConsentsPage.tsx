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
  useDocumentTitle('Consents');
  const query = useQuery({ queryKey: ['consents'], queryFn: () => get<Consent[]>('/api/consents') });

  return (
    <>
      <PageHeader title="Informed consents" subtitle="Created from your clinic's templates and signed by hand." />
      <div className="layout-aside">
        <Panel flush title="Created consents" titleId="created">
          <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
            {query.data?.length === 0 ? (
              <EmptyState icon="clipboard" title="No consents yet" />
            ) : (
              <ul className="list">
                {query.data?.map((consent) => (
                  <li key={consent.id}>
                    <Link className="list__item" to={`/app/consents/${consent.id}`}>
                      <span className="list__main">
                        <span className="list__title">{consent.title}</span>
                        <span className="list__meta">
                          {fullName(consent)} · {consent.language === 'es' ? 'Spanish' : 'English'} · {consent.status === 'signed' ? `signed on ${formatDate(consent.signed_at)}` : `created on ${formatDate(consent.created_at)}`}
                        </span>
                      </span>
                      <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>{consent.status === 'signed' ? 'Signed' : 'Pending'}</Badge>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </QueryState>
        </Panel>
        <Panel title="Create a consent" titleId="new">
          <ConsentCreate />
        </Panel>
      </div>
    </>
  );
}

export function ConsentViewPage() {
  const { id = '' } = useParams();
  const query = useQuery({ queryKey: ['consent', id], queryFn: () => get<Consent>(`/api/consents/${id}`) });
  useDocumentTitle(query.data?.title ?? 'Consent');

  return (
    <>
      <PageHeader
        back={query.data ? { to: `/app/patients/${query.data.patient_id}?tab=admin`, label: fullName(query.data) } : { to: '/app/consents', label: 'Consents' }}
        title={query.data?.title ?? 'Consent'}
      />
      <div className="container--narrow">
        <ConsentDocument id={id} audience="staff" />
      </div>
    </>
  );
}
