import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { PageHeader } from '@/components/ui/Display';
import { SelectField } from '@/components/ui/Field';
import { QueryState, SkeletonRows } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { formatDate, formatTime, fullName, parseDate, toISODate } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { Appointment } from '@/lib/types';

interface Week {
  start: string;
  end: string;
  previous: string;
  next: string;
  days: { date: string; appointments: Appointment[] }[];
}

export default function AgendaPage() {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const week = params.get('week') ?? '';
  const psychologist = params.get('professional') ?? '';
  const today = toISODate(new Date());
  useDocumentTitle('Schedule');

  const query = useQuery({
    queryKey: ['agenda', week, psychologist],
    queryFn: () => get<Week>('/api/appointments', { week, psychologist_id: psychologist }),
    placeholderData: keepPreviousData,
  });

  const go = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value);
    else next.delete(key);
    setParams(next, { replace: key === 'professional' });
  };

  const data = query.data;
  const total = data?.days.reduce((sum, day) => sum + day.appointments.length, 0) ?? 0;
  const range = data ? `${formatDate(data.start, { day: 'numeric', month: 'long' })} – ${formatDate(data.end, { day: 'numeric', month: 'long', year: 'numeric' })}` : '';

  return (
    <>
      <PageHeader
        title="Weekly schedule"
        subtitle={data ? `${range} · ${total === 1 ? '1 appointment' : `${total} appointments`}` : undefined}
        actions={
          <ButtonLink to="/app/schedule/new" variant="primary" icon="plus">
            Book appointment
          </ButtonLink>
        }
      />

      <div className="toolbar">
        <div className="cluster">
          <Button icon="chevronLeft" iconOnly aria-label="Previous week" onClick={() => data && go('week', data.previous)} />
          <Button onClick={() => go('week', '')}>This week</Button>
          <Button icon="chevronRight" iconOnly aria-label="Next week" onClick={() => data && go('week', data.next)} />
        </div>
        <SelectField
          label="Professional"
          id="agenda-psychologist"
          value={psychologist}
          onChange={(event) => go('professional', event.target.value)}
          placeholder="Whole team"
          options={(meta?.psychologists ?? []).map((person) => ({ value: String(person.id), label: person.full_name }))}
        />
      </div>

      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch} skeleton={<SkeletonRows rows={6} />}>
        {data && (
          <div className="week" aria-busy={query.isFetching}>
            {data.days.map((day) => {
              const date = parseDate(day.date);
              const isToday = day.date === today;
              return (
                <section key={day.date} className={isToday ? 'week__day is-today' : 'week__day'} aria-label={formatDate(day.date, { weekday: 'long', day: 'numeric', month: 'long' })}>
                  <header className="week__head">
                    <span className="week__dayname">{date?.toLocaleDateString('en-US', { weekday: 'short' })}</span>
                    <span className="week__date">{date?.getDate()}</span>
                  </header>
                  {day.appointments.length > 0 && (
                    <ul className="week__list">
                      {day.appointments.map((appointment) => (
                        <li key={appointment.id}>
                          <Link className={`slot slot--${appointment.status}`} to={`/app/schedule/${appointment.id}/edit`}>
                            <span className="slot__time">{formatTime(appointment.starts_at)}</span>{' '}
                            <span className="slot__meta">· {labelOf(meta?.modalities, appointment.modality)}</span>
                            <span className="slot__name">{fullName(appointment)}</span>
                            <span className="slot__meta">{labelOf(meta?.appointmentStatuses, appointment.status)}</span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                  <ButtonLink to={`/app/schedule/new?date=${day.date}`} size="sm" variant="quiet" icon="plus" className="week__add">
                    Book
                  </ButtonLink>
                </section>
              );
            })}
          </div>
        )}
      </QueryState>
    </>
  );
}
