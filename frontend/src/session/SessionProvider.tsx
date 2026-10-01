import { useQuery, useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, type ReactNode } from 'react';
import { get, onUnauthorized, post, setCsrfToken } from '@/lib/api';
import type { SessionData, User } from '@/lib/types';
import { useTheme } from './ThemeProvider';

interface SessionValue {
  user: User | null;
  clinicName: string;
  clinicTagline: string;
  demoAccounts: SessionData['demoAccounts'];
  loading: boolean;
  login: (identifier: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const SessionContext = createContext<SessionValue | null>(null);

export const SESSION_KEY = ['session'] as const;

export function SessionProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const { setTheme } = useTheme();

  const { data, isPending } = useQuery({
    queryKey: SESSION_KEY,
    queryFn: () => get<SessionData>('/api/session'),
    staleTime: 5 * 60_000,
  });

  useEffect(() => {
    if (data?.csrfToken) setCsrfToken(data.csrfToken);
    if (data?.user?.theme) setTheme(data.user.theme, false);
  }, [data, setTheme]);

  // A 401 on any screen means the session expired on the server.
  useEffect(
    () =>
      onUnauthorized(() => {
        queryClient.setQueryData<SessionData | undefined>(SESSION_KEY, (current) =>
          current ? { ...current, user: null } : current,
        );
      }),
    [queryClient],
  );

  const applySession = useCallback(
    (session: SessionData) => {
      setCsrfToken(session.csrfToken);
      queryClient.setQueryData(SESSION_KEY, session);
    },
    [queryClient],
  );

  const login = useCallback(
    async (identifier: string, password: string) => {
      const { data: session } = await post<SessionData>('/api/auth/login', { identifier, password });
      // Nothing from the previous session may survive in the cache.
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== 'session' });
      applySession(session);
      return session.user as User;
    },
    [applySession, queryClient],
  );

  const logout = useCallback(async () => {
    let token = '';
    try {
      const { data: result } = await post<{ csrfToken: string }>('/api/auth/logout');
      token = result.csrfToken;
      setCsrfToken(token);
    } finally {
      // Clear the user from the cache first (without waiting for a refetch) so
      // no screen keeps showing data from the closed session.
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== 'session' });
      queryClient.setQueryData<SessionData | undefined>(SESSION_KEY, (current) =>
        current ? { ...current, user: null, csrfToken: token || current.csrfToken } : current,
      );
      void queryClient.invalidateQueries({ queryKey: SESSION_KEY });
    }
  }, [queryClient]);

  const refresh = useCallback(async () => {
    await queryClient.invalidateQueries({ queryKey: SESSION_KEY });
  }, [queryClient]);

  const value = useMemo<SessionValue>(
    () => ({
      user: data?.user ?? null,
      clinicName: data?.clinic.name || 'PsiClinic',
      clinicTagline: data?.clinic.tagline ?? '',
      demoAccounts: data?.demoAccounts ?? [],
      loading: isPending,
      login,
      logout,
      refresh,
    }),
    [data, isPending, login, logout, refresh],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionValue {
  const context = useContext(SessionContext);
  if (!context) throw new Error('useSession must be used inside SessionProvider');
  return context;
}

export function isStaff(user: User | null): boolean {
  return user !== null && user.role !== 'patient';
}

export function homeFor(user: User | null): string {
  if (!user) return '/login';
  return user.role === 'patient' ? '/portal' : '/app';
}
