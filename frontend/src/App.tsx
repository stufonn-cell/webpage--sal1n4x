import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { lazy, Suspense, type ReactNode } from 'react';
import { createBrowserRouter, Navigate, Outlet, RouterProvider, ScrollRestoration, useLocation } from 'react-router';
import { ApiError } from '@/lib/api';
import { ConfirmProvider, LoadingLine, ToastProvider } from '@/components/ui/Feedback';
import { homeFor, isStaff, SessionProvider, useSession } from '@/session/SessionProvider';
import { ThemeProvider } from '@/session/ThemeProvider';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { RouteError } from '@/pages/RouteError';

/*
 * Three areas, each with its own bundle: the public site does not download the
 * clinical record code, and a patient does not download the staff dashboard.
 */
const SiteLayout = lazy(() => import('@/site/SiteLayout'));
const HomePage = lazy(() => import('@/site/HomePage'));
const RequestPage = lazy(() => import('@/site/RequestPage'));
const PrivacyPage = lazy(() => import('@/site/PrivacyPage'));
const TermsPage = lazy(() => import('@/site/TermsPage'));
const LoginPage = lazy(() => import('@/pages/LoginPage'));
const StaffApp = lazy(() => import('@/app/StaffApp'));
const PortalApp = lazy(() => import('@/portal/PortalApp'));

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: (count, error) => !(error instanceof ApiError && error.status < 500 && error.status !== 0) && count < 1,
    },
  },
});

function Loader({ children }: { children: ReactNode }) {
  return <Suspense fallback={<LoadingLine />}>{children}</Suspense>;
}

function Root() {
  return (
    <>
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      <Loader>
        <Outlet />
      </Loader>
      <ScrollRestoration />
    </>
  );
}

/** Guards an area by role. Remembers where the person was trying to go. */
function Guard({ area, children }: { area: 'staff' | 'patient'; children: ReactNode }) {
  const { user, loading } = useSession();
  const location = useLocation();

  if (loading) return <LoadingLine label="Getting your space ready" />;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname, expired: true }} />;

  const allowed = area === 'staff' ? isStaff(user) : user.role === 'patient';
  if (!allowed) return <Navigate to={homeFor(user)} replace />;

  return <>{children}</>;
}

const router = createBrowserRouter([
  {
    element: <Root />,
    errorElement: <RouteError />,
    children: [
      {
        element: <SiteLayout />,
        children: [
          { index: true, element: <HomePage /> },
          { path: 'request-appointment', element: <RequestPage /> },
          { path: 'privacy', element: <PrivacyPage /> },
          { path: 'terms', element: <TermsPage /> },
        ],
      },
      { path: 'login', element: <LoginPage /> },
      {
        // Each area defines its own internal routes (descendant routes).
        path: 'app/*',
        element: (
          <Guard area="staff">
            <StaffApp />
          </Guard>
        ),
      },
      {
        path: 'portal/*',
        element: (
          <Guard area="patient">
            <PortalApp />
          </Guard>
        ),
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]);

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <SessionProvider>
          <ToastProvider>
            <ConfirmProvider>
              <RouterProvider router={router} />
            </ConfirmProvider>
          </ToastProvider>
        </SessionProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
