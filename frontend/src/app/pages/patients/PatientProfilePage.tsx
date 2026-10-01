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
  const tab = params.get('tab') ?? 'resumen';

  const query = useQuery({ queryKey: ['patient', id], queryFn: () => get<PatientBundle>(`/api/patients/${id}`) });
  const bundle = query.data;
  useDocumentTitle(bundle ? fullName(bundle.patient) : 'Paciente');

  const remove = useAction(() => del(`/api/patients/${id}`), {
    invalidate: [['patients'], ['dashboard'], ['meta']],
    onSuccess: () => navigate('/app/pacientes', { replace: true }),
  });

  const setTab = (next: string) => {
    const nextParams = new URLSearchParams(params);
    nextParams.set('tab', next);
    setParams(nextParams, { replace: true });
  };

  const onDelete = async () => {
    if (!bundle) return;
    const ok = await confirm({
      title: `Eliminar la historia de ${bundle.patient.first_name}`,
      text: 'Se eliminarán de forma permanente sus notas, evaluaciones, citas, documentos y facturas. Si el proceso terminó, considera marcarlo como “Alta” en lugar de eliminarlo.',
      confirmLabel: 'Eliminar definitivamente',
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
                {ageFrom(bundle.patient.birth_date) !== null && <span>{ageFrom(bundle.patient.birth_date)} años</span>}
                {bundle.patient.psychologist_name && <span>Con {bundle.patient.psychologist_name}</span>}
                <Badge tone={PATIENT_STATUS_TONE[bundle.patient.status]}>{labelOf(meta?.patientStatuses, bundle.patient.status)}</Badge>
              </div>
            </div>
            <div className="page-header__actions">
              <ButtonLink to={`/app/notas/nueva?paciente=${id}`} variant="primary" icon="note">
                Nueva nota
              </ButtonLink>
              <ButtonLink to={`/app/pacientes/${id}/editar`} icon="edit">
                Editar
              </ButtonLink>
              {user?.role === 'admin' && (
                <Button variant="quiet" iconOnly icon="trash" aria-label="Eliminar paciente" onClick={onDelete} />
              )}
            </div>
          </div>

          {(bundle.patient.risk_level === 'moderate' || bundle.patient.risk_level === 'high') && (
            <div className="risk-banner">
              <Alert tone={bundle.patient.risk_level === 'high' ? 'error' : 'warning'} title={`Riesgo ${labelOf(meta?.riskLevels, bundle.patient.risk_level).toLowerCase()}`}>
                Nivel registrado en la ficha o en la última nota de sesión. El contacto de emergencia está en el resumen.
              </Alert>
            </div>
          )}

          <Tabs
            label="Secciones de la historia"
            active={tab}
            onChange={setTab}
            tabs={[
              { id: 'resumen', label: 'Resumen' },
              { id: 'notas', label: 'Notas', count: bundle.notes.length },
              { id: 'evaluaciones', label: 'Evaluaciones', count: bundle.assessments.length },
              { id: 'diagnosticos', label: 'Diagnósticos', count: bundle.diagnoses.length },
              { id: 'citas', label: 'Citas', count: bundle.appointments.length },
              { id: 'documentos', label: 'Documentos', count: bundle.documents.length },
              { id: 'administrativo', label: 'Administrativo' },
            ]}
          />

          <TabPanel id="resumen" active={tab}>
            <SummaryTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="notas" active={tab}>
            <NotesTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="evaluaciones" active={tab}>
            <AssessmentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="diagnosticos" active={tab}>
            <DiagnosesTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="citas" active={tab}>
            <AppointmentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="documentos" active={tab}>
            <DocumentsTab bundle={bundle} />
          </TabPanel>
          <TabPanel id="administrativo" active={tab}>
            <AdminTab bundle={bundle} />
          </TabPanel>
        </>
      )}
    </QueryState>
  );
}
