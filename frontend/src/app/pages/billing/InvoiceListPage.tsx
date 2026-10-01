import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { Pagination, QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, formatMoney, fullName } from '@/lib/format';
import { INVOICE_STATUS_TONE, labelOf, useMeta } from '@/lib/meta';
import type { Invoice, Page } from '@/lib/types';

export default function InvoiceListPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const status = params.get('status') ?? '';
  const page = Number(params.get('page') ?? 1);
  const currency = meta?.settings.currency;
  useDocumentTitle('Facturación');

  const query = useQuery({
    queryKey: ['invoices', status, page],
    queryFn: () => get<Page<Invoice>>('/api/invoices', { status, page }),
    placeholderData: keepPreviousData,
  });

  const update = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value);
    else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next, { replace: true });
  };

  return (
    <>
      <PageHeader
        title="Facturación"
        actions={
          <ButtonLink to="/app/facturacion/nueva" variant="primary" icon="plus">
            Nueva factura
          </ButtonLink>
        }
      />
      <div className="toolbar">
        <SelectField label="Estado" id="invoice-status" value={status} onChange={(event) => update('status', event.target.value)} options={meta?.invoiceStatuses ?? []} placeholder="Todas" />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState icon="receipt" title="No hay facturas" />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Factura</th>
                    <th scope="col">Paciente</th>
                    <th scope="col" className="num">
                      Total
                    </th>
                    <th scope="col" className="num">
                      Saldo
                    </th>
                    <th scope="col">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((invoice) => {
                    const balance = Number(invoice.total) - Number(invoice.paid ?? 0);
                    return (
                      <tr key={invoice.id}>
                        <td>
                          <Link className="table__link" to={`/app/facturacion/${invoice.id}`}>
                            {invoice.number}
                          </Link>
                          <span className="table__sub">{formatDate(invoice.issued_at)}</span>
                        </td>
                        <td data-label="Paciente">{fullName(invoice)}</td>
                        <td data-label="Total" className="num">
                          {formatMoney(invoice.total, currency)}
                        </td>
                        <td data-label="Saldo" className="num">
                          {formatMoney(balance, currency)}
                        </td>
                        <td data-label="Estado">
                          <Badge tone={INVOICE_STATUS_TONE[invoice.status]}>{labelOf(meta?.invoiceStatuses, invoice.status)}</Badge>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          {query.data && <Pagination page={query.data.page} pages={query.data.pages} total={query.data.total} onChange={(next) => update('page', String(next))} />}
        </QueryState>
      </Panel>
    </>
  );
}
