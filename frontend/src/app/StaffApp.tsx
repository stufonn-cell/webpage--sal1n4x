import { Route, Routes } from 'react-router';
import { ButtonLink } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/Display';
import ProfilePage from '@/pages/ProfilePage';
import { StaffLayout } from './layout/StaffLayout';
import AgendaPage from './pages/agenda/AgendaPage';
import AppointmentFormPage from './pages/agenda/AppointmentFormPage';
import AssessmentFormPage from './pages/assessments/AssessmentFormPage';
import AssessmentListPage from './pages/assessments/AssessmentListPage';
import AssessmentViewPage from './pages/assessments/AssessmentViewPage';
import CatalogPage from './pages/assessments/CatalogPage';
import InvoiceFormPage from './pages/billing/InvoiceFormPage';
import InvoiceListPage from './pages/billing/InvoiceListPage';
import InvoiceViewPage from './pages/billing/InvoiceViewPage';
import { ConsentListPage, ConsentViewPage } from './pages/ConsentsPage';
import DashboardPage from './pages/DashboardPage';
import DocumentsPage from './pages/DocumentsPage';
import NoteFormPage from './pages/notes/NoteFormPage';
import NoteListPage from './pages/notes/NoteListPage';
import NoteViewPage from './pages/notes/NoteViewPage';
import PatientFormPage from './pages/patients/PatientFormPage';
import PatientListPage from './pages/patients/PatientListPage';
import PatientProfilePage from './pages/patients/PatientProfilePage';
import RequestsPage from './pages/RequestsPage';
import AuditPage from './pages/settings/AuditPage';
import ClinicSettingsPage from './pages/settings/ClinicSettingsPage';
import UsersPage from './pages/settings/UsersPage';
import './app.css';

/** Rutas del equipo clinico. Todas viven bajo /app y exigen sesion de staff. */
export default function StaffApp() {
  return (
    <StaffLayout>
      <Routes>
        <Route index element={<DashboardPage />} />
        <Route path="solicitudes" element={<RequestsPage />} />

        <Route path="pacientes" element={<PatientListPage />} />
        <Route path="pacientes/nuevo" element={<PatientFormPage />} />
        <Route path="pacientes/:id" element={<PatientProfilePage />} />
        <Route path="pacientes/:id/editar" element={<PatientFormPage />} />

        <Route path="agenda" element={<AgendaPage />} />
        <Route path="agenda/nueva" element={<AppointmentFormPage />} />
        <Route path="agenda/:id/editar" element={<AppointmentFormPage />} />

        <Route path="notas" element={<NoteListPage />} />
        <Route path="notas/nueva" element={<NoteFormPage />} />
        <Route path="notas/:id" element={<NoteViewPage />} />
        <Route path="notas/:id/editar" element={<NoteFormPage />} />

        <Route path="evaluaciones" element={<AssessmentListPage />} />
        <Route path="evaluaciones/catalogo" element={<CatalogPage />} />
        <Route path="evaluaciones/nueva" element={<AssessmentFormPage />} />
        <Route path="evaluaciones/:id" element={<AssessmentViewPage />} />

        <Route path="consentimientos" element={<ConsentListPage />} />
        <Route path="consentimientos/:id" element={<ConsentViewPage />} />
        <Route path="documentos" element={<DocumentsPage />} />

        <Route path="facturacion" element={<InvoiceListPage />} />
        <Route path="facturacion/nueva" element={<InvoiceFormPage />} />
        <Route path="facturacion/:id" element={<InvoiceViewPage />} />

        <Route path="ajustes" element={<ClinicSettingsPage />} />
        <Route path="ajustes/usuarios" element={<UsersPage />} />
        <Route path="ajustes/auditoria" element={<AuditPage />} />
        <Route path="perfil" element={<ProfilePage />} />

        <Route
          path="*"
          element={<EmptyState icon="alert" title="Esta sección no existe" text="Puede que el enlace haya cambiado." action={<ButtonLink to="/app" size="sm">Volver al inicio</ButtonLink>} />}
        />
      </Routes>
    </StaffLayout>
  );
}
