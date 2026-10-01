import { useQuery } from '@tanstack/react-query';
import { useNavigate, useParams, useSearchParams } from 'react-router';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Alert, Avatar, Badge } from '@/components/ui/Display';
import { QueryState, SkeletonRows, useConfirm } from '@/components/ui/Feedback';
import { TabPanel, Tabs } from '@/components/ui/Tabs';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { del, get } from '@/lib/api';
import { ageFrom, fullName } from '@/lib/format';
import { labelOf, PATIENT_STATUS_TONE, useMeta } from '@/lib/meta';
import { useSession } from '@/session/SessionProvider';
import { useAction } from '../../useAction';
import { AdminTab, DocumentsTab } from './profile/AdminTab';
import { DiagnosesTab } from './profile/DiagnosesTab';
import { AppointmentsTab, AssessmentsTab, NotesTab } from './profile/RecordTabs';
import { SummaryTab } from './profile/SummaryTab';
import type { PatientBundle } from './profile/types';

export default function PatientProfilePage() {
  const { id = '' } = useParams();
  const [params, setParams] = useSearchParams();
  const { data: meta } = useMeta();
  const { user } = useSession();
  const confirm = useConfirm();
  const navigate = useNavigate();
  const tab = params.get('tab') ?? 'summary';

  const query = useQuery({ queryKey: ['patient', id], queryFn: () => get<PatientBundle>(`/api/patients/${id}`) });
  const bundle = query.data;
  useDocumentTitle(bundle ? fullName(bundle.patient) : 'Patient');

  const remove = useAction(() => del(`/api/patients/${id}`), {
    invalidate: [['patients'], ['dashboard'], ['meta']],
    onSuccess: () => navigate('/app/patients', { replace: true }),
  });

  const setTab = (next: string) => {
    const nextParams = new URLSearchParams(params);
    nextParams.set('tab', next);
    setParams(nextParams, { replace: true });
  };

  const onDelete = async () => {
    if (!bundle) return;
    const ok = await confirm({
      title: `Delete ${bundle.patient.first_name}'s record`,
      text: 'Their notes, assessments, appointments, documents and invoices will be permanently deleted. If treatment has ended, consider marking them as “Discharged” instead.',
      confirmLabel: 'Delete permanently',
      danger: true,
    });
    if (ok) await remove.run(undefined).catch(() => undefined);
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch} skeleton={<SkeletonRows rows={10} />}>
      {bundle && (
        <>
          <div className="profile-head enter">
            <Avatar name={fullName(bundle.patient)} size="lg" />
            <div className="profile-head__main">
              <h1 className="page-header__title">{fullName(bundle.patient)}</h1>
              <div className="profile-head__meta">
                <span>{bundle.patient.record_number}</span>
                {ageFrom(bundle.patient.birth_date) !== null && <span>{ageFrom(bundle.patient.birth_date)} years old</span>}
                {bundle.patient.psychologist_name && <span>With {bundle.patient.psychologist_name}</span>}
                <Badge tone={PATIENT_STATUS_TONE[bundle.patient.status]}>{labelOf(meta?.patientStatuses, bundle.patient.status)}</Badge>
              </div>
            </div>
            <div className="page-header__actions">
              <ButtonLink to={`/app/notes/new?patient=${id}`} variant="primary" icon="note">
                New note
              </ButtonLink>
              <ButtonLink to={`/app/patients/${id}/edit`} icon="edit">
                Edit
              </ButtonLink>
              {user?.role === 'admin' && (
                <Button variant="quiet" iconOnly icon="trash" aria-label="Delete patient" onClick={onDelete} />
              )}
            </div>
          </div>

          {(bundle.patient.risk_level === 'moderate' || bundle.patient.risk_level === 'high') && (
            <div className="risk-banner">
              <Alert tone={bundle.patient.risk_level === 'high' ? 'error' : 'warning'} title={`${labelOf(meta?.riskLevels, bundle.patient.risk_level)} risk`}>
                Level recorded on the patient file or in the latest session note. The emergency contact is in the summary.
              </Alert>
            </div>
          )}

          <Tabs
            label="Record sections"
            active={tab}
            onChange={setTab}
            tabs={[
              { id: 'summary', label: 'Summary' },
              { id: 'notes', label: 'Notes', count: bundle.notes.length },
              { id: 'assessments', label: 'Assessments', count: bundle.assessments.length },
              { id: 'diagnoses', label: 'Diagnoses', count: bundle.diagnoses.length },
              { id: 'appointments', label: 'Appointments', count: bundle.appointments.length },
              { id: 'documents', label: 'Documents', count: bundle.documents.length },
              { id: 'admin', label: 'Administrative' },
            ]}
          />

          <TabPanel id="summary" active={tab}>
            <SummaryTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="notes" active={tab}>
            <NotesTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="assessments" active={tab}>
            <AssessmentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="diagnoses" active={tab}>
            <DiagnosesTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="appointments" active={tab}>
            <AppointmentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="documents" active={tab}>
            <DocumentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="admin" active={tab}>
            <AdminTab bundle={bundle} />
          </TabPanel>
        </>
      )}
    </QueryState>
  );
}
