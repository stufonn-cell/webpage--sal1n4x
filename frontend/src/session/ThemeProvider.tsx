import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import type { Theme } from '@/lib/types';

/**
 * Tema claro/oscuro. La preferencia del sistema manda hasta que la persona
 * elige uno; en la app clinica la eleccion tambien se guarda en su cuenta.
 * localStorage solo guarda "light" o "dark": no es informacion personal.
 */

interface ThemeValue {
  theme: Theme;
  setTheme: (theme: Theme, remember?: boolean) => void;
  toggle: () => Theme;
}

const STORAGE_KEY = 'psiclinic-theme';
const ThemeContext = createContext<ThemeValue | null>(null);

function initialTheme(): Theme {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') return stored;
  } catch {
    // Almacenamiento bloqueado: se usa la preferencia del sistema.
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(initialTheme);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
  }, [theme]);

  const setTheme = useCallback((next: Theme, remember = true) => {
    setThemeState(next);
    if (!remember) return;
    try {
      window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Sin almacenamiento el tema dura lo que la pestana.
    }
  }, []);

  const toggle = useCallback(() => {
    const next: Theme = theme === 'dark' ? 'light' : 'dark';
    setTheme(next);
    return next;
  }, [setTheme, theme]);

  const value = useMemo(() => ({ theme, setTheme, toggle }), [setTheme, theme, toggle]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeValue {
  const context = useContext(ThemeContext);
  if (!context) throw new Error('useTheme debe usarse dentro de ThemeProvider');
  return context;
}
