import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, post } from '@/lib/api';
import { formatDate, formatMoney, fullName, toISODate } from '@/lib/format';
import { INVOICE_STATUS_TONE, labelOf, useMeta } from '@/lib/meta';
import type { Invoice } from '@/lib/types';
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
    validate: (values): Record<string, string> => (Number(values.amount) > 0 ? {} : { amount: 'Escribe un monto mayor que cero.' }),
    onSubmit: async (values) => {
      const result = await post<{ balance: number }>(`/api/invoices/${invoiceId}/payments`, values);
      toast.success(result.message ?? 'Pago registrado.');
      form.setValues({ ...values, amount: result.data.balance > 0 ? String(result.data.balance) : '', reference: '' });
      await invalidate(['invoice', String(invoiceId)], ['invoices'], ['dashboard']);
    },
  });

  const overpaying = Number(form.values.amount) > balance && balance > 0;

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <TextField
        label={`Monto (${currency})`}
        type="number"
        min={0}
        step={1000}
        hint={overpaying ? `Supera el saldo de ${formatMoney(balance, currency)}.` : undefined}
        {...form.bind('amount')}
      />
      <TextField label="Fecha" type="date" {...form.bind('paid_at')} />
      <SelectField label="Medio de pago" options={meta?.paymentMethods ?? []} {...form.bind('method')} />
      <TextField label="Referencia" optional {...form.bind('reference')} />
      <div>
        <Button type="submit" variant="primary" loading={form.submitting}>
          Registrar pago
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
  useDocumentTitle(detail?.invoice.number ?? 'Factura');

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {detail && (
        <>
          <PageHeader
            back={{ to: '/app/facturacion', label: 'Facturación' }}
            title={`Factura ${detail.invoice.number}`}
            subtitle={`Emitida el ${formatDate(detail.invoice.issued_at)}${detail.invoice.due_at ? ` · vence el ${formatDate(detail.invoice.due_at)}` : ''}`}
            actions={
              <Button icon="print" onClick={() => window.print()}>
                Imprimir
              </Button>
            }
          />
          <div className="layout-aside">
            <article className="document-sheet">
              <header className="document-sheet__head">
                <div>
                  <p className="serif document-sheet__clinic">{detail.clinic.name}</p>
                  <p className="xsmall muted">{[detail.clinic.address, detail.clinic.phone, detail.clinic.email].filter(Boolean).join(' · ')}</p>
                </div>
                <Badge tone={INVOICE_STATUS_TONE[detail.invoice.status]}>{labelOf(meta?.invoiceStatuses, detail.invoice.status)}</Badge>
              </header>
              <dl className="facts document-sheet__facts">
                <div>
                  <dt>Paciente</dt>
                  <dd>
                    <Link to={`/app/pacientes/${detail.invoice.patient_id}`}>{fullName(detail.invoice)}</Link>
                  </dd>
                </div>
                <div>
                  <dt>Documento</dt>
                  <dd>{detail.invoice.document_id || '—'}</dd>
                </div>
                <div>
                  <dt>Historia</dt>
                  <dd>{detail.invoice.record_number}</dd>
                </div>
              </dl>
              <div className="table-wrap">
                <table className="table">
                  <thead>
                    <tr>
                      <th scope="col">Concepto</th>
                      <th scope="col" className="num">
                        Cant.
                      </th>
                      <th scope="col" className="num">
                        Valor
                      </th>
                      <th scope="col" className="num">
                        Importe
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {detail.items.map((item) => (
                      <tr key={item.id}>
                        <td>{item.description}</td>
                        <td className="num">{Number(item.quantity)}</td>
                        <td className="num">{formatMoney(item.unit_price, detail.currency)}</td>
                        <td className="num">{formatMoney(item.amount, detail.currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="totals document-sheet__signature">
                <div>
                  <span>Subtotal</span>
                  <span className="tabular">{formatMoney(detail.invoice.subtotal, detail.currency)}</span>
                </div>
                <div>
                  <span>Impuesto</span>
                  <span className="tabular">{formatMoney(detail.invoice.tax, detail.currency)}</span>
                </div>
                <div className="totals__grand">
                  <span>Total</span>
                  <span className="tabular">{formatMoney(detail.invoice.total, detail.currency)}</span>
                </div>
                <div>
                  <span>Saldo pendiente</span>
                  <strong className="tabular">{formatMoney(detail.balance, detail.currency)}</strong>
                </div>
              </div>
              {detail.invoice.notes && <p className="small soft">{detail.invoice.notes}</p>}
            </article>

            <div className="section-gap no-print">
              {detail.balance > 0 && detail.invoice.status !== 'void' && (
                <Panel title="Registrar pago" titleId="pago">
                  <PaymentForm invoiceId={detail.invoice.id} balance={detail.balance} currency={detail.currency} />
                </Panel>
              )}
              <Panel title="Pagos recibidos" titleId="pagos" flush>
                {detail.payments.length === 0 ? (
                  <EmptyState icon="receipt" title="Sin pagos registrados" />
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
