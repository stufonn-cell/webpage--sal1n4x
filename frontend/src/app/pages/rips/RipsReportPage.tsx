import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Alert, Badge, EmptyState, Facts, PageHeader, Panel } from '@/components/ui/Display';
import { FormAlert, PasswordField, SelectField, TextField } from '@/components/ui/Field';
import { QueryState, useConfirm, useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useForm } from '@/hooks/useForm';
import { del, get, post } from '@/lib/api';
import { formatDate, formatDateTime } from '@/lib/format';
import { labelOf, useMeta } from '@/lib/meta';
import type { RipsReport, RipsValidation } from '@/lib/types';
import { useSession } from '@/session/SessionProvider';
import { useAction, useInvalidate } from '../../useAction';
import { RIPS_STATUS_TONE } from './ripsTone';

/**
 * Sends the report to the MUV Docker API. The SISPRO password travels with
 * this request only: the backend never stores it.
 */
function SendForm({ report }: { report: RipsReport }) {
  const { data: meta } = useMeta();
  const { user } = useSession();
  const toast = useToast();
  const invalidate = useInvalidate();

  const form = useForm({
    initial: { document_type: user?.document_type ?? 'CC', document_number: user?.document_number ?? '', password: '' },
    validate: (values) => {
      const errors: Record<string, string> = {};
      if (!values.document_number.trim()) errors.document_number = 'Enter the document of the SISPRO user.';
      if (!values.password) errors.password = 'Enter the SISPRO password.';
      return errors;
    },
    onSubmit: async (values) => {
      const result = await post<RipsValidation>(`/api/rips/${report.id}/send`, values);
      form.set('password', '');
      if (result.data.accepted) toast.success(result.message ?? 'The Ministry validated the RIPS.');
      else toast.error(result.message ?? 'The validator rejected the RIPS.');
      await invalidate(['rips'], ['rips-report', String(report.id)]);
    },
  });

  return (
    <form className="inline-form" onSubmit={form.handleSubmit} noValidate>
      <FormAlert message={form.formError} />
      <p className="muted small">
        Sign in with the SISPRO user of the reporting party. The password is used for this submission only and is never stored.
      </p>
      <SelectField label="Document type" options={meta?.rips.documentTypes ?? []} {...form.bind('document_type')} />
      <TextField label="Document number" inputMode="numeric" autoComplete="username" {...form.bind('document_number')} />
      <PasswordField label="SISPRO password" autoComplete="current-password" {...form.bind('password')} />
      <div>
        <Button type="submit" variant="primary" icon="upload" loading={form.submitting} loadingLabel="Sending">
          Send to the validator
        </Button>
      </div>
    </form>
  );
}

function ValidationResults({ validation }: { validation: RipsValidation }) {
  const rejected = validation.results.filter((result) => result.class.toUpperCase().startsWith('RECHAZ'));
  const notices = validation.results.filter((result) => !result.class.toUpperCase().startsWith('RECHAZ'));

  return (
    <div className="stack">
      {validation.accepted ? (
        <Alert tone="success" title="Validated by the Ministry">
          CUV <span className="rips-cuv">{validation.cuv}</span>
          {validation.receivedAt && ` · received ${formatDateTime(validation.receivedAt)}`}
        </Alert>
      ) : (
        <Alert tone="error" title="Rejected by the validator">
          Fix the data below, delete this report and generate it again. Its consultations become available again once it is deleted.
        </Alert>
      )}

      {validation.results.length > 0 && (
        <div className="table-wrap">
          <table className="table table--stack">
            <thead>
              <tr>
                <th scope="col">Type</th>
                <th scope="col">Code</th>
                <th scope="col">Message</th>
                <th scope="col">Field</th>
              </tr>
            </thead>
            <tbody>
              {[...rejected, ...notices].map((result, index) => (
                <tr key={`${result.code}-${index}`}>
                  <td data-label="Type">
                    <Badge tone={result.class.toUpperCase().startsWith('RECHAZ') ? 'danger' : 'info'}>
                      {result.class.toUpperCase().startsWith('RECHAZ') ? 'Rejection' : 'Notice'}
                    </Badge>
                  </td>
                  <td data-label="Code">{result.code}</td>
                  <td data-label="Message">
                    {result.description}
                    {result.notes && <span className="table__sub">{result.notes}</span>}
                  </td>
                  <td data-label="Field" className="rips-path">
                    {result.path || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="muted small rips-note">The Ministry returns these messages in Spanish; they are shown exactly as received.</p>
        </div>
      )}
    </div>
  );
}

export default function RipsReportPage() {
  const { id = '' } = useParams();
  const { data: meta } = useMeta();
  const confirm = useConfirm();
  const navigate = useNavigate();

  const query = useQuery({
    queryKey: ['rips-report', id],
    queryFn: () => get<RipsReport>(`/api/rips/${id}`),
  });
  const report = query.data;
  useDocumentTitle(report ? `RIPS ${report.note_number}` : 'RIPS report');

  const remove = useAction(() => del(`/api/rips/${id}`), {
    invalidate: [['rips']],
    onSuccess: () => navigate('/app/rips', { replace: true }),
  });

  const onDelete = async () => {
    const ok = await confirm({
      title: 'Delete report',
      text: 'The report will be deleted and its consultations will be available for a new RIPS. Do it after fixing the data the validator rejected.',
      confirmLabel: 'Delete',
      danger: true,
    });
    if (ok) await remove.run(undefined).catch(() => undefined);
  };

  return (
    <>
      <PageHeader
        back={{ to: '/app/rips', label: 'RIPS reports' }}
        title={report ? `RIPS ${report.note_number}` : 'RIPS report'}
        subtitle={report ? `${formatDate(report.period_start)} – ${formatDate(report.period_end)}` : undefined}
        actions={
          report && (
            <div className="cluster">
              <a className="btn" href={`/api/rips/${report.id}/download`} download>
                <Icon name="download" size={17} />
                <span className="btn__label">Download JSON</span>
              </a>
              {report.status !== 'validated' && (
                <Button variant="danger" icon="trash" onClick={onDelete} loading={remove.pending}>
                  Delete
                </Button>
              )}
            </div>
          )
        }
      />

      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        {report && (
          <div className="layout-aside">
            <div className="section-gap">
              <Panel title="Summary" titleId="rips-summary">
                <Facts
                  items={[
                    ['Status', <Badge tone={RIPS_STATUS_TONE[report.status]}>{labelOf(meta?.rips.statuses, report.status)}</Badge>],
                    ['Consultations', report.services_count],
                    ['Patients', report.users_count],
                    ['Reporting party (NIT)', report.payload.numDocumentoIdObligado],
                    ['Type', 'RIPS without invoice (RS)'],
                    ['Generated', `${formatDateTime(report.created_at)}${report.created_by_name ? ` · ${report.created_by_name}` : ''}`],
                    ['Sent', report.sent_at ? `${formatDateTime(report.sent_at)}${report.sent_by_name ? ` · ${report.sent_by_name}` : ''}` : null],
                    ['CUV', report.cuv ? <span className="rips-cuv">{report.cuv}</span> : null],
                  ]}
                />
              </Panel>

              {report.validation_result && (
                <Panel title="Validation result" titleId="rips-validation">
                  <ValidationResults validation={report.validation_result} />
                </Panel>
              )}

              <Panel title="JSON content" titleId="rips-json">
                <details className="rips-json">
                  <summary>Show the file exactly as it will be validated</summary>
                  <pre>{JSON.stringify(report.payload, null, 2)}</pre>
                </details>
              </Panel>
            </div>

            <div className="section-gap">
              {report.status === 'validated' ? (
                <Panel title="Validated" titleId="rips-done">
                  <p>The Ministry already validated this report. Keep the CUV with your records.</p>
                </Panel>
              ) : report.validatorConfigured ? (
                <Panel title={report.status === 'rejected' ? 'Send again' : 'Send to the Ministry'} titleId="rips-send">
                  <SendForm report={report} />
                </Panel>
              ) : (
                <Panel title="Send to the Ministry" titleId="rips-send">
                  <EmptyState
                    icon="upload"
                    title="No validator configured"
                    text={
                      <>
                        Download the JSON and load it in the Ministry's validator, or add the address of your MUV Docker API in{' '}
                        <Link className="table__link" to="/app/settings#rips">
                          the RIPS settings
                        </Link>
                        .
                      </>
                    }
                  />
                </Panel>
              )}
            </div>
          </div>
        )}
      </QueryState>
    </>
  );
}
