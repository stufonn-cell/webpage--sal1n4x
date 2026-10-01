import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { post } from '@/lib/api';
import { formatMoney, toISODate } from '@/lib/format';
import { useMeta } from '@/lib/meta';
import { useInvalidate } from '../../useAction';

interface Line {
  key: number;
  description: string;
  quantity: string;
  unit_price: string;
}

export default function InvoiceFormPage() {
  const { data: meta } = useMeta();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const invalidate = useInvalidate();
  const currency = meta?.settings.currency ?? 'COP';
  useDocumentTitle('New invoice');

  const [lines, setLines] = useState<Line[]>([
    { key: 1, description: 'Psychotherapy session', quantity: '1', unit_price: String(meta?.settings.default_fee ?? '') },
  ]);

  const form = useForm({
    initial: { patient_id: params.get('patient') ?? '', issued_at: toISODate(new Date()), due_at: '', tax_rate: '0', notes: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.patient_id) errors.patient_id = 'Choose who the invoice is for.';
      if (!lines.some((line) => line.description.trim())) errors.items = 'Add at least one item.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post<{ id: number }>('/api/invoices', {
        ...values,
        items: lines.map(({ description, quantity, unit_price }) => ({ description, quantity, unit_price })),
      });
      toast.success(result.message ?? 'Invoice created.');
      await invalidate(['invoices'], ['dashboard'], ['meta'], ['patient', values.patient_id]);
      navigate(`/app/billing/${result.data.id}`);
    },
  });

  const updateLine = (key: number, field: keyof Omit<Line, 'key'>, value: string) =>
    setLines((current) => current.map((line) => (line.key === key ? { ...line, [field]: value } : line)));

  const subtotal = lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_price) || 0), 0);
  const tax = subtotal * ((Number(form.values.tax_rate) || 0) / 100);

  return (
    <>
      <PageHeader back={{ to: '/app/billing', label: 'Billing' }} title="New invoice" subtitle={meta ? `It will be issued as ${meta.nextInvoiceNumber}` : undefined} />
      <form onSubmit={form.handleSubmit} noValidate>
        <Panel>
          <FormAlert message={form.formError} />
          <div className="form-grid">
            <SelectField
              label="Patient"
              placeholder="Choose a patient"
              wrapperClassName="span-full"
              options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              {...form.bind('patient_id')}
            />
            <TextField label="Issue date" type="date" {...form.bind('issued_at')} />
            <TextField label="Due date" type="date" optional {...form.bind('due_at')} />
            <TextField label="Tax (%)" type="number" min={0} max={100} step={0.5} {...form.bind('tax_rate')} />
          </div>

          <div className="form-section">
            <h2 className="form-section__title">Items</h2>
            {form.errors.items && (
              <p className="field__error" role="alert" id="items">
                {form.errors.items}
              </p>
            )}
            <div className="table-wrap">
              <table className="table invoice-lines">
                <thead>
                  <tr>
                    <th scope="col">Description</th>
                    <th scope="col">Quantity</th>
                    <th scope="col">Unit price</th>
                    <th scope="col" className="num">
                      Amount
                    </th>
                    <th scope="col">
                      <span className="visually-hidden">Remove</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, index) => (
                    <tr key={line.key}>
                      <td>
                        <input
                          className="input"
                          aria-label={`Description for item ${index + 1}`}
                          value={line.description}
                          onChange={(event) => updateLine(line.key, 'description', event.target.value)}
                        />
                      </td>
                      <td className="invoice-lines__qty">
                        <input
                          className="input"
                          type="number"
                          min={0}
                          step={1}
                          aria-label={`Quantity for item ${index + 1}`}
                          value={line.quantity}
                          onChange={(event) => updateLine(line.key, 'quantity', event.target.value)}
                        />
                      </td>
                      <td className="invoice-lines__price">
                        <input
                          className="input"
                          type="number"
                          min={0}
                          step={1000}
                          aria-label={`Unit price for item ${index + 1}`}
                          value={line.unit_price}
                          onChange={(event) => updateLine(line.key, 'unit_price', event.target.value)}
                        />
                      </td>
                      <td className="num">{formatMoney((Number(line.quantity) || 0) * (Number(line.unit_price) || 0), currency)}</td>
                      <td>
                        <Button
                          size="sm"
                          variant="quiet"
                          iconOnly
                          icon="trash"
                          aria-label={`Remove item ${index + 1}`}
                          disabled={lines.length === 1}
                          onClick={() => setLines((current) => current.filter((item) => item.key !== line.key))}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Button
              size="sm"
              icon="plus"
              variant="quiet"
              onClick={() => setLines((current) => [...current, { key: Date.now(), description: '', quantity: '1', unit_price: '' }])}
            >
              Add item
            </Button>

            <div className="totals">
              <div>
                <span>Subtotal</span>
                <span className="tabular">{formatMoney(subtotal, currency)}</span>
              </div>
              <div>
                <span>Tax</span>
                <span className="tabular">{formatMoney(tax, currency)}</span>
              </div>
              <div className="totals__grand">
                <span>Total</span>
                <span className="tabular">{formatMoney(subtotal + tax, currency)}</span>
              </div>
            </div>
          </div>

          <div className="form-section">
            <TextAreaField label="Notes" optional rows={2} {...form.bind('notes')} />
          </div>

          <div className="form-actions">
            <ButtonLink to="/app/billing" variant="quiet">
              Cancel
            </ButtonLink>
            <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Creating">
              Create invoice
            </Button>
          </div>
        </Panel>
      </form>
    </>
  );
}
