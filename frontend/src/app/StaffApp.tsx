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
import RipsListPage from './pages/rips/RipsListPage';
import RipsReportPage from './pages/rips/RipsReportPage';
import AuditPage from './pages/settings/AuditPage';
import ClinicSettingsPage from './pages/settings/ClinicSettingsPage';
import UsersPage from './pages/settings/UsersPage';
import './app.css';

/** Clinical team routes. They all live under /app and require a staff session. */
export default function StaffApp() {
  return (
    <StaffLayout>
      <Routes>
        <Route index element={<DashboardPage />} />
        <Route path="requests" element={<RequestsPage />} />

        <Route path="patients" element={<PatientListPage />} />
        <Route path="patients/new" element={<PatientFormPage />} />
        <Route path="patients/:id" element={<PatientProfilePage />} />
        <Route path="patients/:id/edit" element={<PatientFormPage />} />

        <Route path="schedule" element={<AgendaPage />} />
        <Route path="schedule/new" element={<AppointmentFormPage />} />
        <Route path="schedule/:id/edit" element={<AppointmentFormPage />} />

        <Route path="notes" element={<NoteListPage />} />
        <Route path="notes/new" element={<NoteFormPage />} />
        <Route path="notes/:id" element={<NoteViewPage />} />
        <Route path="notes/:id/edit" element={<NoteFormPage />} />

        <Route path="assessments" element={<AssessmentListPage />} />
        <Route path="assessments/catalog" element={<CatalogPage />} />
        <Route path="assessments/new" element={<AssessmentFormPage />} />
        <Route path="assessments/:id" element={<AssessmentViewPage />} />

        <Route path="consents" element={<ConsentListPage />} />
        <Route path="consents/:id" element={<ConsentViewPage />} />
        <Route path="documents" element={<DocumentsPage />} />

        <Route path="billing" element={<InvoiceListPage />} />
        <Route path="billing/new" element={<InvoiceFormPage />} />
        <Route path="billing/:id" element={<InvoiceViewPage />} />

        <Route path="rips" element={<RipsListPage />} />
        <Route path="rips/:id" element={<RipsReportPage />} />

        <Route path="settings" element={<ClinicSettingsPage />} />
        <Route path="settings/users" element={<UsersPage />} />
        <Route path="settings/audit" element={<AuditPage />} />
        <Route path="profile" element={<ProfilePage />} />

        <Route
          path="*"
          element={
            <EmptyState
              icon="alert"
              title="This section doesn't exist"
              text="The link may have changed."
              action={
                <ButtonLink to="/app" size="sm">
                  Back to home
                </ButtonLink>
              }
            />
          }
        />
      </Routes>
    </StaffLayout>
  );
}
