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
          patient_id: params.get('patient') ?? '',
          psychologist_id: defaultPsychologist,
          date: params.get('date') ?? toISODate(new Date()),
          time: params.get('time') ?? '09:00',
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
      if (!values.patient_id) errors.patient_id = 'Choose a patient.';
      if (!values.psychologist_id) errors.psychologist_id = 'Choose who will see the patient.';
      if (!values.date) errors.date = 'Choose a date.';
      if (!values.time) errors.time = 'Choose a time.';
      if (values.meeting_url && !/^https?:\/\//i.test(values.meeting_url)) errors.meeting_url = 'The link must start with https://';
      return errors;
    },
    onSubmit: async (values) => {
      const result = appointment
        ? await put<{ week: string }>(`/api/appointments/${appointment.id}`, values)
        : await post<{ week: string }>('/api/appointments', values);
      toast.success(result.message ?? 'Appointment saved.');
      await invalidate(['agenda'], ['dashboard'], ['patient', values.patient_id]);
      navigate(`/app/schedule?week=${result.data.week}`);
    },
  });

  const changeStatus = useAction((status: string) => post(`/api/appointments/${appointment?.id}/status`, { status }), {
    invalidate: [['agenda'], ['dashboard'], ['appointment', String(appointment?.id)]],
    onSuccess: (result) => form.set('status', (result.data as { status: string }).status),
  });

  const remove = useAction(() => del(`/api/appointments/${appointment?.id}`), {
    invalidate: [['agenda'], ['dashboard']],
    onSuccess: () => navigate('/app/schedule'),
  });

  const onDelete = async () => {
    const ok = await confirm({
      title: 'Delete appointment',
      text: "If the appointment didn't happen, you can mark it as cancelled to keep the history. Do you still want to delete it?",
      confirmLabel: 'Delete',
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
              label="Patient"
              placeholder="Choose a patient"
              wrapperClassName="span-full"
              options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
              {...form.bind('patient_id')}
            />
            <SelectField
              label="Professional"
              options={(meta?.psychologists ?? []).map((person) => ({ value: String(person.id), label: person.full_name }))}
              {...form.bind('psychologist_id')}
            />
            <SelectField label="Modality" options={meta?.modalities ?? []} {...form.bind('modality')} />
            <TextField label="Date" type="date" {...form.bind('date')} />
            <TextField label="Time" type="time" step={300} {...form.bind('time')} />
            <TextField label="Duration (minutes)" type="number" min={15} max={480} step={5} {...form.bind('duration')} />
            <TextField label="Session type" optional placeholder="First visit, follow-up…" {...form.bind('session_type')} />
            {isOnline ? (
              <TextField
                label="Video call link"
                optional
                type="url"
                placeholder="https://"
                wrapperClassName="span-full"
                hint="The patient will see it in their portal."
                {...form.bind('meeting_url')}
              />
            ) : (
              <TextField label="Office or room" optional {...form.bind('location')} />
            )}
            <TextField label={`Fee (${meta?.settings.currency ?? 'COP'})`} type="number" min={0} step={1000} {...form.bind('fee')} />
            {!appointment && <SelectField label="Status" options={meta?.appointmentStatuses ?? []} {...form.bind('status')} />}
            <TextAreaField label="Internal notes" optional rows={3} wrapperClassName="span-full" hint="Not shown to the patient." {...form.bind('notes')} />
          </div>

          <div className="form-actions">
            <ButtonLink to="/app/schedule" variant="quiet">
              Cancel
            </ButtonLink>
            <Button type="submit" variant="primary" loading={form.submitting} loadingLabel="Saving">
              {appointment ? 'Save changes' : 'Book appointment'}
            </Button>
          </div>
        </Panel>
      </form>

      {appointment && (
        <div className="section-gap">
          <Panel title="Appointment status" titleId="status">
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
                <ButtonLink to={`/app/notes/new?patient=${appointment.patient_id}&appointment=${appointment.id}`} variant="primary" size="sm" icon="note">
                  Write the note for this session
                </ButtonLink>
              )}
            </div>
          </Panel>
          <Alert tone="info">When you save, we check that the professional doesn't have another appointment at the same time.</Alert>
          <div>
            <Button variant="danger" size="sm" icon="trash" onClick={onDelete} loading={remove.pending}>
              Delete appointment
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
  useDocumentTitle(id ? 'Edit appointment' : 'Book appointment');

  const query = useQuery({
    queryKey: ['appointment', id],
    queryFn: () => get<AppointmentDetail>(`/api/appointments/${id}`),
    enabled: Boolean(id),
  });

  return (
    <>
      <PageHeader back={{ to: '/app/schedule', label: 'Schedule' }} title={id ? 'Edit appointment' : 'Book appointment'} />
      <QueryState isPending={metaPending || (Boolean(id) && query.isPending)} error={query.error} onRetry={query.refetch}>
        {meta && (!id || query.data) && <AppointmentForm appointment={query.data} />}
      </QueryState>
    </>
  );
}
