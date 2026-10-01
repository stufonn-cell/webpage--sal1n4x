import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Badge, EmptyState, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { get, post } from '@/lib/api';
import { formatDate, formatDateTime, formatMoney, pluralize, toISODate } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { RipsOverview, RipsPreview } from '@/lib/types';
import { useSession } from '@/session/SessionProvider';
import { useInvalidate } from '../../useAction';
import { RIPS_STATUS_TONE } from './ripsTone';

/** Default period: the previous calendar month, the usual reporting cycle. */
function previousMonth(): { from: string; to: string } {
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
  const last = new Date(today.getFullYear(), today.getMonth(), 0);
  return { from: toISODate(first), to: toISODate(last) };
}

function PreviewTable({ preview }: { preview: RipsPreview }) {
  const { data: meta } = useMeta();
  const missing = preview.items.length - preview.readyCount;

  if (preview.items.length === 0) {
    return (
      <EmptyState
        icon="calendar"
        title="No completed appointments to report"
        text="Only appointments marked as completed that are not in a previous report appear here."
      />
    );
  }

  return (
    <>
      <p className="rips-summary" role="status">
        <strong>{pluralize(preview.readyCount, 'consultation')} ready</strong> for {pluralize(preview.usersCount, 'patient')}
        {missing > 0 && <> · {pluralize(missing, 'consultation')} with missing data (left out until fixed)</>}
      </p>
      <div className="table-wrap">
        <table className="table table--stack">
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Consultation</th>
              <th scope="col">ICD-10</th>
              <th scope="col" className="num">
                Value
              </th>
              <th scope="col">RIPS</th>
            </tr>
          </thead>
          <tbody>
            {preview.items.map((item) => (
              <tr key={item.appointment_id}>
                <td>
                  <Link className="table__link" to={`/app/patients/${item.patient_id}`}>
                    {item.patient_name}
                  </Link>
                  <span className="table__sub">{item.record_number}</span>
                </td>
                <td data-label="Consultation">
                  {formatDateTime(item.starts_at)}
                  <span className="table__sub">
                    CUPS {item.cups} · {item.professional_name}
                  </span>
                </td>
                <td data-label="ICD-10">{item.diagnosis ?? '—'}</td>
                <td data-label="Value" className="num">
                  {formatMoney(item.value, meta?.settings.currency)}
                </td>
                <td data-label="RIPS">
                  {item.issues.length === 0 ? (
                    <Badge tone="success">Ready</Badge>
                  ) : (
                    <>
                      <Badge tone="warning">Missing data</Badge>
                      <ul className="issue-list">
                        {item.issues.map((issue) => (
                          <li key={issue}>{issue}</li>
                        ))}
                      </ul>
                      <span className="cluster">
                        <Link className="table__link small" to={`/app/patients/${item.patient_id}/edit`}>
                          Edit patient
                        </Link>
                        <Link className="table__link small" to={`/app/patients/${item.patient_id}?tab=diagnoses`}>
                          Diagnoses
                        </Link>
                      </span>
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}

function NewReport({ overview }: { overview: RipsOverview }) {
  const toast = useToast();
  const navigate = useNavigate();
  const invalidate = useInvalidate();
  const [preview, setPreview] = useState<RipsPreview | null>(null);
  const [generating, setGenerating] = useState(false);
  const [generateError, setGenerateError] = useState('');

  const form = useForm({
    initial: previousMonth(),
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.from) errors.from = 'Choose the start date.';
      if (!values.to) errors.to = 'Choose the end date.';
      if (values.from && values.to && values.from > values.to) errors.from = 'The start date is after the end date.';
      return errors;
    },
    onSubmit: async (values) => {
      setGenerateError('');
      const result = await post<RipsPreview>('/api/rips/preview', values);
      setPreview(result.data);
    },
  });

  const generate = async () => {
    setGenerating(true);
    setGenerateError('');
    try {
      const result = await post<{ id: number }>('/api/rips', form.values);
      toast.success(result.message ?? 'RIPS generated.');
      await invalidate(['rips']);
      navigate(`/app/rips/${result.data.id}`);
    } catch (error) {
      setGenerateError(error instanceof Error ? error.message : 'We could not generate the RIPS.');
    } finally {
      setGenerating(false);
    }
  };

  const blocked = overview.configIssues.length > 0;

  return (
    <Panel title="New report" titleId="new-report" subtitle={`It will be numbered ${overview.nextNoteNumber}.`}>
      <form
        className="toolbar"
        onSubmit={form.handleSubmit}
        onChange={() => setPreview(null)}
        noValidate
        aria-label="Reporting period"
      >
        <TextField label="From" type="date" {...form.bind('from')} />
        <TextField label="To" type="date" {...form.bind('to')} />
        <Button type="submit" icon="search" loading={form.submitting}>
          Review period
        </Button>
      </form>
      <FormAlert message={form.formError || generateError} />

      {preview && (
        <div className="stack">
          <PreviewTable preview={preview} />
          {preview.readyCount > 0 && (
            <div className="form-actions">
              {blocked && <span className="muted small">Complete the RIPS settings first.</span>}
              <Button variant="primary" icon="file" onClick={generate} loading={generating} disabled={blocked}>
                Generate RIPS {overview.nextNoteNumber}
              </Button>
            </div>
          )}
        </div>
      )}
    </Panel>
  );
}

export default function RipsListPage() {
  const { user } = useSession();
  const { data: meta } = useMeta();
  useDocumentTitle('RIPS reports');
  const isAdmin = user?.role === 'admin';

  const query = useQuery({
    queryKey: ['rips'],
    queryFn: () => get<RipsOverview>('/api/rips'),
    enabled: isAdmin,
  });

  return (
    <>
      <PageHeader
        title="RIPS reports"
        subtitle="Consultations reported without invoice to Colombia's Ministry of Health (Resolution 2275 of 2023)."
        actions={
          isAdmin && (
            <ButtonLink to="/app/settings#rips" icon="settings" variant="quiet">
              RIPS settings
            </ButtonLink>
          )
        }
      />

      {!isAdmin ? (
        <EmptyState icon="lock" title="Only administrators can manage RIPS" text="Ask an administrator to generate or send the reports." />
      ) : (
        <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
          {query.data && (
            <div className="section-gap">
              {query.data.configIssues.length > 0 && (
                <Alert tone="warning" title="Before generating a RIPS">
                  <ul className="issue-list">
                    {query.data.configIssues.map((issue) => (
                      <li key={issue}>{issue}</li>
                    ))}
                  </ul>
                  <Link className="table__link" to="/app/settings#rips">
                    Open the RIPS settings
                  </Link>
                </Alert>
              )}

              <NewReport overview={query.data} />

              <Panel title="Generated reports" titleId="generated-reports" flush>
                {query.data.reports.length === 0 ? (
                  <EmptyState icon="file" title="No reports yet" text="Review a period and generate the first RIPS." />
                ) : (
                  <div className="table-wrap">
                    <table className="table table--stack">
                      <thead>
                        <tr>
                          <th scope="col">Number</th>
                          <th scope="col">Period</th>
                          <th scope="col" className="num">
                            Consultations
                          </th>
                          <th scope="col">Status</th>
                          <th scope="col">CUV</th>
                        </tr>
                      </thead>
                      <tbody>
                        {query.data.reports.map((report) => (
                          <tr key={report.id}>
                            <td>
                              <Link className="table__link" to={`/app/rips/${report.id}`}>
                                RIPS {report.note_number}
                              </Link>
                              <span className="table__sub">
                                {formatDate(report.created_at)}
                                {report.created_by_name && ` · ${report.created_by_name}`}
                              </span>
                            </td>
                            <td data-label="Period">
                              {formatDate(report.period_start)} – {formatDate(report.period_end)}
                            </td>
                            <td data-label="Consultations" className="num">
                              {report.services_count}
                              <span className="table__sub">{pluralize(report.users_count, 'patient')}</span>
                            </td>
                            <td data-label="Status">
                              <Badge tone={RIPS_STATUS_TONE[report.status]}>{labelOf(meta?.rips.statuses, report.status)}</Badge>
                            </td>
                            <td data-label="CUV" className="rips-cuv">
                              {report.cuv ?? '—'}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </Panel>
            </div>
          )}
        </QueryState>
      )}
    </>
  );
}
