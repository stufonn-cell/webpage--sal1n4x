import { useQuery } from '@tanstack/react-query';
import { useNavigate, useParams, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, SelectField, TextAreaField, TextField } from '@/components/ui/Field';
import { QueryState, useConfirm, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { del, get, post, put } from '@/lib/api';
import { formatDateTime, toISODate } from '@/lib/format';
import { APPOINTMENT_STATUS_TONE, labelOf, useMeta } from '@/lib/meta';
import type { Appointment } from '@/lib/types';
import { useSession } from '@/session/SessionProvider';
import { useAction, useInvalidate } from '../../useAction';

type Values = {
  patient_id: string;
  psychologist_id: string;
  date: string;
  time: string;
  duration: string;
  modality: string;
  status: string;
  session_type: string;
  location: string;
  meeting_url: string;
  fee: string;
  notes: string;
};

type AppointmentDetail = Appointment & { date: string; time: string; duration: number };

function AppointmentForm({ appointment }: { appointment?: AppointmentDetail }) {
  const { data: meta } = useMeta();
  const { user } = useSession();
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const toast = useToast();
  const confirm = useConfirm();
  const invalidate = useInvalidate();

  const defaultPsychologist =
    user && (user.role === 'psychologist' || user.role === 'admin') ? String(user.id) : String(meta?.psychologists[0]?.id ?? '');

  const form = useForm<Values>({
    initial: appointment
      ? {
          patient_id: String(appointment.patient_id),
          psychologist_id: String(appointment.psychologist_id),
          date: appointment.date,
          time: appointment.time,
          duration: String(appointment.duration),
          modality: appointment.modality,
          status: appointment.status,
          session_type: appointment.session_type ?? '',
          location: appointment.location ?? '',
          meeting_url: appointment.meeting_url ?? '',
          fee: String(appointment.fee ?? ''),
          notes: appointment.notes ?? '',
        }
      : {
          patient_id: params.get('paciente') ?? '',
          psychologist_id: defaultPsychologist,
          date: params.get('fecha') ?? toISODate(new Date()),
          time: params.get('hora') ?? '09:00',
          duration: String(meta?.settings.session_duration ?? 50),
          modality: 'in_person',
          status: 'scheduled',
          session_type: '',
          location: '',
          meeting_url: '',
          fee: String(meta?.settings.default_fee ?? ''),
          notes: '',
        },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.patient_id) errors.patient_id = 'Elige un paciente.';
      if (!values.psychologist_id) errors.psychologist_id = 'Elige quién atiende.';
      if (!values.date) errors.date = 'Elige la fecha.';
      if (!values.time) errors.time = 'Elige la hora.';
      if (values.meeting_url && !/^https?:\/\//i.test(values.meeting_url)) errors.meeting_url = 'El enlace debe empezar por https://';
      return errors;
    },
    onSubmit: async (values) => {
      const result = appointment
        ? await put<{ week: string }>(`/api/appointments/${appointment.id}`, values)
        : await post<{ week: string }>('/api/appointments', values);
      toast.success(result.message ?? 'Cita guardada.');
      await invalidate(['agenda'], ['dashboard'], ['patient', values.patient_id]);
      navigate(`/app/agenda?semana=${result.data.week}`);
    },
  });

  const changeStatus = useAction((status: string) => post(`/api/appointments/${appointment?.id}/status`, { status }), {
    invalidate: [['agenda'], ['dashboard'], ['appointment', String(appointment?.id)]],
    onSuccess: (result) => form.set('status', (result.data as { status: string }).status),
  });

  const remove = useAction(() => del(`/api/appointments/${appointment?.id}`), {
    invalidate: [['agenda'], ['dashboard']],
    onSuccess: () => navigate('/app/agenda'),
  });

  const onDelete = async () => {
    const ok = await confirm({
      title: 'Eliminar cita',
      text: 'Si la cita no se realizó, puedes marcarla como cancelada para conservar el historial. ¿Quieres eliminarla igualmente?',
      confirmLabel: 'Eliminar',
      danger: true,
    });
    if (ok) await remove.run(undefined).catch(() => undefined);
  };

  const isOnline = form.values.modality !== 'in_person';

  return (
    <div className="layout-aside">
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
            <SelectField
              label="Profesional"
              options={(meta?.psychologists ?? []).map((person) => ({ value: String(person.id), label: person.full_name }))}
              {...form.bind('psychologist_id')}
            />
            <SelectField label="Modalidad" options={meta?.modalities ?? []} {...form.bind('modality')} />
            <TextField label="Fecha" type="date" {...form.bind('date')} />
            <TextField label="Hora" type="time" step={300} {...form.bind('time')} />
            <TextField label="Duración (minutos)" type="number" min={15} max={480} step={5} {...form.bind('duration')} />
            <TextField label="Tipo de sesión" optional placeholder="Primera consulta, seguimiento…" {...form.bind('session_type')} />
            {isOnline ? (
              <TextField
                label="Enlace de la videollamada"
                optional
                type="url"
                placeholder="https://"
                wrapperClassName="span-full"
                hint="El paciente lo verá en su portal."
                {...form.bind('meeting_url')}
              />
            ) : (
              <TextField label="Consultorio o sala" optional {...form.bind('location')} />
            )}
            <TextField label={`Valor (${meta?.settings.currency ?? 'COP'})`} type="number" min={0} step={1000} {...form.bind('fee')} />
            {!appointment && <SelectField label="Estado" options={meta?.appointmentStatuses ?? []} {...form.bind('status')} />}
            <TextAreaField label="Notas internas" optional rows={3} wrapperClassName="span-full" hint="No se muestran al paciente." {...form.bind('notes')} />
          </div>

          <div className="form-actions">
            <ButtonLink to="/app/agenda" variant="quiet">
              Cancelar
            </ButtonLink>
            <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Guardando">
              {appointment ? 'Guardar cambios' : 'Agendar cita'}
            </Button>
          </div>
        </Panel>
      </form>

      {appointment && (
        <div className="section-gap">
          <Panel title="Estado de la cita" titleId="estado">
            <div className="stack">
              <div>
                <Badge tone={APPOINTMENT_STATUS_TONE[form.values.status]}>{labelOf(meta?.appointmentStatuses, form.values.status)}</Badge>
              </div>
              <p className="small muted">{formatDateTime(appointment.starts_at)}</p>
              <div className="cluster">
                {['confirmed', 'completed', 'no_show', 'cancelled']
                  .filter((status) => status !== form.values.status)
                  .map((status) => (
                    <Button key={status} size="sm" onClick={() => changeStatus.run(status).catch(() => undefined)} disabled={changeStatus.pending}>
                      {labelOf(meta?.appointmentStatuses, status)}
                    </Button>
                  ))}
              </div>
              {form.values.status === 'completed' && (
                <ButtonLink to={`/app/notas/nueva?paciente=${appointment.patient_id}&cita=${appointment.id}`} variant="primary" size="sm" icon="note">
                  Escribir la nota de esta sesión
                </ButtonLink>
              )}
            </div>
          </Panel>
          <Alert tone="info">Al guardar, revisamos que el profesional no tenga otra cita a la misma hora.</Alert>
          <div>
            <Button variant="danger" size="sm" icon="trash" onClick={onDelete} loading={remove.pending}>
              Eliminar cita
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function AppointmentFormPage() {
  const { id } = useParams();
  const { data: meta, isPending: metaPending } = useMeta();
  useDocumentTitle(id ? 'Editar cita' : 'Agendar cita');

  const query = useQuery({
    queryKey: ['appointment', id],
    queryFn: () => get<AppointmentDetail>(`/api/appointments/${id}`),
    enabled: Boolean(id),
  });

  return (
    <>
      <PageHeader back={{ to: '/app/agenda', label: 'Agenda' }} title={id ? 'Editar cita' : 'Agendar cita'} />
      <QueryState isPending={metaPending || (Boolean(id) && query.isPending)} error={query.error} onRetry={query.refetch}>
        {meta && (!id || query.data) && <AppointmentForm appointment={query.data} />}
      </QueryState>
    </>
  );
}
