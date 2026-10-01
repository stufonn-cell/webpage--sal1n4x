import { Link } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, Panel } from '@/components/ui/Display';
import { formatDate, formatMoney } from '@/lib/format';
import { INVOICE_STATUS_TONE, labelOf, useMeta } from '@/lib/meta';
import { ConsentCreate } from '../../../components/ConsentCreate';
import { DocumentTable } from '../../../components/DocumentTable';
import { DocumentUpload } from '../../../components/DocumentUpload';
import type { PatientBundle } from './types';

export function DocumentsTab({ bundle }: { bundle: PatientBundle }) {
  return (
    <div className="layout-aside">
      <Panel title="Attached documents" titleId="docs" flush>
        <DocumentTable documents={bundle.documents} />
      </Panel>
      <Panel title="Attach a document" titleId="upload">
        <DocumentUpload patientId={bundle.patient.id} />
      </Panel>
    </div>
  );
}

export function AdminTab({ bundle }: { bundle: PatientBundle }) {
  const { data: meta } = useMeta();
  const currency = meta?.settings.currency;

  return (
    <div className="layout-aside">
      <div className="section-gap">
        <Panel
          title="Invoices"
          titleId="invoices"
          flush
          actions={
            <ButtonLink to={`/app/billing/new?patient=${bundle.patient.id}`} size="sm" icon="plus">
              New invoice
            </ButtonLink>
          }
        >
          {bundle.invoices.length === 0 ? (
            <EmptyState icon="receipt" title="No invoices" />
          ) : (
            <ul className="list">
              {bundle.invoices.map((invoice) => (
                <li key={invoice.id}>
                  <Link className="list__item" to={`/app/billing/${invoice.id}`}>
                    <span className="list__main">
                      <span className="list__title">{invoice.number}</span>
                      <span className="list__meta">
                        {formatDate(invoice.issued_at)} · paid {formatMoney(invoice.paid, currency)} of {formatMoney(invoice.total, currency)}
                      </span>
                    </span>
                    <Badge tone={INVOICE_STATUS_TONE[invoice.status]}>{labelOf(meta?.invoiceStatuses, invoice.status)}</Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        <Panel title="Consents" titleId="consents" flush>
          {bundle.consents.length === 0 ? (
            <EmptyState icon="clipboard" title="No consents" />
          ) : (
            <ul className="list">
              {bundle.consents.map((consent) => (
                <li key={consent.id}>
                  <Link className="list__item" to={`/app/consents/${consent.id}`}>
                    <span className="list__main">
                      <span className="list__title">{consent.title}</span>
                      <span className="list__meta">
                        {consent.language === 'es' ? 'Spanish · ' : 'English · '}
                        {consent.status === 'signed' ? `signed on ${formatDate(consent.signed_at)}` : `created on ${formatDate(consent.created_at)}`}
                      </span>
                    </span>
                    <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>
                      {consent.status === 'signed' ? 'Signed' : 'Pending'}
                    </Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>

      <Panel title="Create a consent" titleId="create-consent">
        <ConsentCreate patientId={bundle.patient.id} />
      </Panel>
    </div>
  );
}
