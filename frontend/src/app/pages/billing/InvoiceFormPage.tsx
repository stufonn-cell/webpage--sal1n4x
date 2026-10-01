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
  useDocumentTitle('Nueva factura');

  const [lines, setLines] = useState<Line[]>([
    { key: 1, description: 'Sesión de psicoterapia', quantity: '1', unit_price: String(meta?.settings.default_fee ?? '') },
  ]);

  const form = useForm({
    initial: { patient_id: params.get('paciente') ?? '', issued_at: toISODate(new Date()), due_at: '', tax_rate: '0', notes: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.patient_id) errors.patient_id = 'Elige a quién se factura.';
      if (!lines.some((line) => line.description.trim())) errors.items = 'Agrega al menos un concepto.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post<{ id: number }>('/api/invoices', {
        ...values,
        items: lines.map(({ description, quantity, unit_price }) => ({ description, quantity, unit_price })),
      });
      toast.success(result.message ?? 'Factura generada.');
      await invalidate(['invoices'], ['dashboard'], ['meta'], ['patient', values.patient_id]);
      navigate(`/app/facturacion/${result.data.id}`);
    },
  });

  const updateLine = (key: number, field: keyof Omit<Line, 'key'>, value: string) =>
    setLines((current) => current.map((line) => (line.key === key ? { ...line, [field]: value } : line)));

  const subtotal = lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_price) || 0), 0);
  const tax = subtotal * ((Number(form.values.tax_rate) || 0) / 100);

  return (
    <>
      <PageHeader back={{ to: '/app/facturacion', label: 'Facturación' }} title="Nueva factura" subtitle={meta ? `Se emitirá como ${meta.nextInvoiceNumber}` : undefined} />
      <form onSubmit={form.handleSubmit} noValidate>
        <Panel>
          <FormAlert message={form.formError} />
          <div className="form-grid">
            <SelectField
              label="Paciente"
              placeholder="Elige un paciente"
              wrapperClassName="span-full"
              options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              {...form.bind('patient_id')}
            />
            <TextField label="Fecha de emisión" type="date" {...form.bind('issued_at')} />
            <TextField label="Vence" type="date" optional {...form.bind('due_at')} />
            <TextField label="Impuesto (%)" type="number" min={0} max={100} step={0.5} {...form.bind('tax_rate')} />
          </div>

          <div className="form-section">
            <h2 className="form-section__title">Conceptos</h2>
            {form.errors.items && (
              <p className="field__error" role="alert" id="items">
                {form.errors.items}
              </p>
            )}
            <div className="table-wrap">
              <table className="table invoice-lines">
                <thead>
                  <tr>
                    <th scope="col">Descripción</th>
                    <th scope="col">Cantidad</th>
                    <th scope="col">Valor unitario</th>
                    <th scope="col" className="num">
                      Importe
                    </th>
                    <th scope="col">
                      <span className="visually-hidden">Quitar</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, index) => (
                    <tr key={line.key}>
                      <td>
                        <input
                          className="input"
                          aria-label={`Descripción del concepto ${index + 1}`}
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
                          aria-label={`Cantidad del concepto ${index + 1}`}
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
                          aria-label={`Valor unitario del concepto ${index + 1}`}
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
                          aria-label={`Quitar el concepto ${index + 1}`}
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
              Agregar concepto
            </Button>

            <div className="totals">
              <div>
                <span>Subtotal</span>
                <span className="tabular">{formatMoney(subtotal, currency)}</span>
              </div>
              <div>
                <span>Impuesto</span>
                <span className="tabular">{formatMoney(tax, currency)}</span>
              </div>
              <div className="totals__grand">
                <span>Total</span>
                <span className="tabular">{formatMoney(subtotal + tax, currency)}</span>
              </div>
            </div>
          </div>

          <div className="form-section">
            <TextAreaField label="Observaciones" optional rows={2} {...form.bind('notes')} />
          </div>

          <div className="form-actions">
            <ButtonLink to="/app/facturacion" variant="quiet">
              Cancelar
            </ButtonLink>
            <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Generando">
              Generar factura
            </Button>
          </div>
        </Panel>
      </form>
    </>
  );
}
