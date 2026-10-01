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
      <Panel title="Documentos adjuntos" titleId="docs" flush>
        <DocumentTable documents={bundle.documents} />
      </Panel>
      <Panel title="Adjuntar documento" titleId="subir">
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
          title="Facturas"
          titleId="facturas"
          flush
          actions={
            <ButtonLink to={`/app/facturacion/nueva?paciente=${bundle.patient.id}`} size="sm" icon="plus">
              Nueva factura
            </ButtonLink>
          }
        >
          {bundle.invoices.length === 0 ? (
            <EmptyState icon="receipt" title="Sin facturas" />
          ) : (
            <ul className="list">
              {bundle.invoices.map((invoice) => (
                <li key={invoice.id}>
                  <Link className="list__item" to={`/app/facturacion/${invoice.id}`}>
                    <span className="list__main">
                      <span className="list__title">{invoice.number}</span>
                      <span className="list__meta">
                        {formatDate(invoice.issued_at)} · pagado {formatMoney(invoice.paid, currency)} de {formatMoney(invoice.total, currency)}
                      </span>
                    </span>
                    <Badge tone={INVOICE_STATUS_TONE[invoice.status]}>{labelOf(meta?.invoiceStatuses, invoice.status)}</Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        <Panel title="Consentimientos" titleId="consentimientos" flush>
          {bundle.consents.length === 0 ? (
            <EmptyState icon="clipboard" title="Sin consentimientos" />
          ) : (
            <ul className="list">
              {bundle.consents.map((consent) => (
                <li key={consent.id}>
                  <Link className="list__item" to={`/app/consentimientos/${consent.id}`}>
                    <span className="list__main">
                      <span className="list__title">{consent.title}</span>
                      <span className="list__meta">
                        {consent.status === 'signed' ? `Firmado el ${formatDate(consent.signed_at)}` : `Generado el ${formatDate(consent.created_at)}`}
                      </span>
                    </span>
                    <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>
                      {consent.status === 'signed' ? 'Firmado' : 'Pendiente'}
                    </Badge>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>

      <Panel title="Generar consentimiento" titleId="generar">
        <ConsentCreate patientId={bundle.patient.id} />
      </Panel>
    </div>
  );
}
