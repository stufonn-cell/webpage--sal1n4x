import { useQuery } from '@tanstack/react-query';
import { Button } from '@/components/ui/Button';
import { Alert, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, put } from '@/lib/api';
import { useSession } from '@/session/SessionProvider';
import { useInvalidate } from '../../useAction';

type Settings = Record<string, string>;

function SettingsForm({ settings, readOnly }: { settings: Settings; readOnly: boolean }) {
  const toast = useToast();
  const invalidate = useInvalidate();
  const form = useForm<Settings>({
    initial: settings,
    onSubmit: async (values) => {
      const result = await put('/api/settings', values);
      toast.success(result.message ?? 'Configuración guardada.');
      await invalidate(['settings'], ['meta'], ['public-site'], ['session']);
    },
  });

  const field = (name: string) => ({ ...form.bind(name), disabled: readOnly });

  return (
    <form onSubmit={form.handleSubmit} noValidate>
      <Panel>
        <FormAlert message={form.formError} />
        {readOnly && <Alert tone="info">Solo una cuenta de administración puede cambiar esta configuración.</Alert>}

        <section className="form-section">
          <h2 className="form-section__title">La clínica</h2>
          <p className="form-section__intro">Estos datos aparecen en el sitio público, en las facturas y en las notas impresas.</p>
          <div className="form-grid">
            <TextField label="Nombre" {...field('clinic_name')} />
            <TextField label="Frase descriptiva" optional {...field('clinic_tagline')} />
            <TextAreaField
              label="Presentación en el sitio"
              optional
              rows={3}
              wrapperClassName="span-full"
              hint="Dos o tres frases que expliquen cómo acompañan. Aparece en la portada."
              maxLength={800}
              {...field('clinic_about')}
            />
            <TextField label="Correo de contacto" type="email" {...field('clinic_email')} />
            <TextField label="Teléfono" type="tel" optional {...field('clinic_phone')} />
            <TextField label="WhatsApp" optional hint="Solo números, con indicativo. Ej.: 573001234567. Vacío para ocultarlo." {...field('whatsapp_number')} />
            <TextField label="Dirección" optional {...field('clinic_address')} />
            <TextField label="Línea de emergencias" hint="Se muestra en el aviso de ayuda inmediata del sitio." {...field('crisis_line')} />
          </div>
        </section>

        <section className="form-section">
          <h2 className="form-section__title">Agenda y facturación</h2>
          <div className="form-grid">
            <TextField label="Duración de la sesión (minutos)" type="number" min={15} {...field('session_duration')} />
            <TextField label="Inicio de la jornada" type="time" {...field('working_hours_start')} />
            <TextField label="Fin de la jornada" type="time" {...field('working_hours_end')} />
            <TextField label="Tarifa por defecto" type="number" min={0} hint="No se publica en el sitio." {...field('default_fee')} />
            <TextField label="Moneda" maxLength={3} {...field('currency')} />
            <TextField label="Horas sugeridas para firmar una nota" type="number" min={1} {...field('note_lock_hours')} />
          </div>
        </section>

        {!readOnly && (
          <div className="form-actions">
            <Button type="submit" variant="primary" loading={form.submitting}>
              Guardar configuración
            </Button>
          </div>
        )}
      </Panel>
    </form>
  );
}

export default function ClinicSettingsPage() {
  const { user } = useSession();
  useDocumentTitle('Configuración');
  const query = useQuery({ queryKey: ['settings'], queryFn: () => get<Settings>('/api/settings') });

  return (
    <>
      <PageHeader title="Configuración de la clínica" />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        {query.data && <SettingsForm settings={query.data} readOnly={user?.role !== 'admin'} />}
      </QueryState>
    </>
  );
}
