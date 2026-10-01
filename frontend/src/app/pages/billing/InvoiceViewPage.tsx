import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, post } from '@/lib/api';
import { documentText } from '@/lib/documentText';
import { formatDate, formatMoney, fullName, toISODate } from '@/lib/format';
import { INVOICE_STATUS_TONE, labelOf, useMeta } from '@/lib/meta';
import type { Invoice } from '@/lib/types';
import { DocumentLanguageSelect, useDocumentLanguage } from '../../components/DocumentLanguageSelect';
import { useInvalidate } from '../../useAction';

interface InvoiceDetail {
  invoice: Invoice;
  items: { id: number; description: string; quantity: number; unit_price: number; amount: number }[];
  payments: { id: number; paid_at: string; amount: number; method: string; reference: string | null }[];
  balance: number;
  currency: string;
  clinic: { name: string; address: string; email: string; phone: string };
}

function PaymentForm({ invoiceId, balance, currency }: { invoiceId: number; balance: number; currency: string }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const form = useForm({
    initial: { amount: balance > 0 ? String(balance) : '', paid_at: toISODate(new Date()), method: 'transfer', reference: '' },
    validate: (values): Record<string, string> => (Number(values.amount) > 0 ? {} : { amount: 'Enter an amount greater than zero.' }),
    onSubmit: async (values) => {
      const result = await post<{ balance: number }>(`/api/invoices/${invoiceId}/payments`, values);
      toast.success(result.message ?? 'Payment recorded.');
      form.setValues({ ...values, amount: result.data.balance > 0 ? String(result.data.balance) : '', reference: '' });
      await invalidate(['invoice', String(invoiceId)], ['invoices'], ['dashboard']);
    },
  });

  const overpaying = Number(form.values.amount) > balance && balance > 0;

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <TextField
        label={`Amount (${currency})`}
        type="number"
        min={0}
        step={1000}
        hint={overpaying ? `This is more than the ${formatMoney(balance, currency)} balance.` : undefined}
        {...form.bind('amount')}
      />
      <TextField label="Date" type="date" {...form.bind('paid_at')} />
      <SelectField label="Payment method" options={meta?.paymentMethods ?? []} {...form.bind('method')} />
      <TextField label="Reference" optional {...form.bind('reference')} />
      <div>
        <Button type="submit" variant="primary" loading={form.submitting}>
          Record payment
        </Button>
      </div>
    </form>
  );
}

export default function InvoiceViewPage() {
  const { id = '' } = useParams();
  const { data: meta } = useMeta();
  const query = useQuery({ queryKey: ['invoice', id], queryFn: () => get<InvoiceDetail>(`/api/invoices/${id}`) });
  const detail = query.data;
  const [language, setLanguage] = useDocumentLanguage();
  const t = documentText(language);
  const money = (amount: number | string) => formatMoney(amount, detail?.currency, t.locale);
  useDocumentTitle(detail?.invoice.number ?? 'Invoice');

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && (
        <>
          <PageHeader
            back={{ to: '/app/billing', label: 'Billing' }}
            title={`Invoice ${detail.invoice.number}`}
            subtitle={`Issued on ${formatDate(detail.invoice.issued_at)}${detail.invoice.due_at ? ` · due on ${formatDate(detail.invoice.due_at)}` : ''}`}
            actions={
              <>
                <DocumentLanguageSelect value={language} onChange={setLanguage} />
                <Button icon="print" onClick={() => window.print()}>
                  Print
                </Button>
              </>
            }
          />
          <div className="layout-aside">
            <article className="document-sheet" lang={language}>
              <header className="document-sheet__head">
                <div>
                  <p className="serif document-sheet__clinic">{detail.clinic.name}</p>
                  <p className="xsmall muted">{[detail.clinic.address, detail.clinic.phone, detail.clinic.email].filter(Boolean).join(' · ')}</p>
                  <p className="document-sheet__title">{t.invoice.title(detail.invoice.number)}</p>
                </div>
                <Badge tone={INVOICE_STATUS_TONE[detail.invoice.status]}>{t.invoice.statuses[detail.invoice.status] ?? detail.invoice.status}</Badge>
              </header>
              <dl className="facts document-sheet__facts">
                <div>
                  <dt>{t.patient}</dt>
                  <dd>
                    <Link to={`/app/patients/${detail.invoice.patient_id}`}>{fullName(detail.invoice)}</Link>
                  </dd>
                </div>
                <div>
                  <dt>{t.idDocument}</dt>
                  <dd>{detail.invoice.document_id || '—'}</dd>
                </div>
                <div>
                  <dt>{t.recordNumber}</dt>
                  <dd>{detail.invoice.record_number}</dd>
                </div>
                <div>
                  <dt>{t.invoice.issuedOn}</dt>
                  <dd>{formatDate(detail.invoice.issued_at, undefined, t.locale)}</dd>
                </div>
                {detail.invoice.due_at && (
                  <div>
                    <dt>{t.invoice.dueOn}</dt>
                    <dd>{formatDate(detail.invoice.due_at, undefined, t.locale)}</dd>
                  </div>
                )}
              </dl>
              <div className="table-wrap">
                <table className="table">
                  <thead>
                    <tr>
                      <th scope="col">{t.invoice.item}</th>
                      <th scope="col" className="num">
                        {t.invoice.quantity}
                      </th>
                      <th scope="col" className="num">
                        {t.invoice.price}
                      </th>
                      <th scope="col" className="num">
                        {t.invoice.amount}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {detail.items.map((item) => (
                      <tr key={item.id}>
                        <td>{item.description}</td>
                        <td className="num">{Number(item.quantity)}</td>
                        <td className="num">{money(item.unit_price)}</td>
                        <td className="num">{money(item.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="totals document-sheet__signature">
                <div>
                  <span>{t.invoice.subtotal}</span>
                  <span className="tabular">{money(detail.invoice.subtotal)}</span>
                </div>
                <div>
                  <span>{t.invoice.tax}</span>
                  <span className="tabular">{money(detail.invoice.tax)}</span>
                </div>
                <div className="totals__grand">
                  <span>{t.invoice.total}</span>
                  <span className="tabular">{money(detail.invoice.total)}</span>
                </div>
                <div>
                  <span>{t.invoice.balance}</span>
                  <strong className="tabular">{money(detail.balance)}</strong>
                </div>
              </div>
              {detail.invoice.notes && <p className="small soft">{detail.invoice.notes}</p>}
            </article>

            <div className="section-gap no-print">
              {detail.balance > 0 && detail.invoice.status !== 'void' && (
                <Panel title="Record a payment" titleId="payment">
                  <PaymentForm invoiceId={detail.invoice.id} balance={detail.balance} currency={detail.currency} />
                </Panel>
              )}
              <Panel title="Payments received" titleId="payments" flush>
                {detail.payments.length === 0 ? (
                  <EmptyState icon="receipt" title="No payments recorded" />
                ) : (
                  <ul className="list">
                    {detail.payments.map((payment) => (
                      <li key={payment.id} className="list__item">
                        <span className="list__main">
                          <span className="list__title">{formatMoney(payment.amount, detail.currency)}</span>
                          <span className="list__meta">
                            {formatDate(payment.paid_at)} · {labelOf(meta?.paymentMethods, payment.method)}
                            {payment.reference && ` · ${payment.reference}`}
                          </span>
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </Panel>
            </div>
          </div>
        </>
      )}
    </QueryState>
  );
}
