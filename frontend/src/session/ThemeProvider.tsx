import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import type { Theme } from '@/lib/types';

/**
 * Light/dark theme. The system preference wins until the person picks one; in
 * the clinical app the choice is also saved to their account.
 * localStorage only stores "light" or "dark": it is not personal information.
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
    // Storage is blocked: fall back to the system preference.
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
      // Without storage the theme lasts as long as the tab.
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
  if (!context) throw new Error('useTheme must be used inside ThemeProvider');
  return context;
}
