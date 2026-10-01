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
  useDocumentTitle('Billing');

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
        title="Billing"
        actions={
          <ButtonLink to="/app/billing/new" variant="primary" icon="plus">
            New invoice
          </ButtonLink>
        }
      />
      <div className="toolbar">
        <SelectField label="Status" id="invoice-status" value={status} onChange={(event) => update('status', event.target.value)} options={meta?.invoiceStatuses ?? []} placeholder="All" />
      </div>
      <Panel flush>
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data?.rows.length === 0 ? (
            <EmptyState icon="receipt" title="No invoices yet" />
          ) : (
            <div className="table-wrap">
              <table className="table table--stack">
                <thead>
                  <tr>
                    <th scope="col">Invoice</th>
                    <th scope="col">Patient</th>
                    <th scope="col" className="num">
                      Total
                    </th>
                    <th scope="col" className="num">
                      Balance
                    </th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data?.rows.map((invoice) => {
                    const balance = Number(invoice.total) - Number(invoice.paid ?? 0);
                    return (
                      <tr key={invoice.id}>
                        <td>
                          <Link className="table__link" to={`/app/billing/${invoice.id}`}>
                            {invoice.number}
                          </Link>
                          <span className="table__sub">{formatDate(invoice.issued_at)}</span>
                        </td>
                        <td data-label="Patient">{fullName(invoice)}</td>
                        <td data-label="Total" className="num">
                          {formatMoney(invoice.total, currency)}
                        </td>
                        <td data-label="Balance" className="num">
                          {formatMoney(balance, currency)}
                        </td>
                        <td data-label="Status">
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
