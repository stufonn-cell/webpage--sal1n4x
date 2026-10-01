import { put } from '@/lib/api';
import { useSession } from '@/session/SessionProvider';
import { useTheme } from '@/session/ThemeProvider';
import { Icon } from './Icon';

export function ThemeToggle() {
  const { theme, toggle } = useTheme();
  const { user } = useSession();
  const next = theme === 'dark' ? 'claro' : 'oscuro';

  const onClick = () => {
    const value = toggle();
    // Si hay sesion, la preferencia viaja con la cuenta a otros equipos.
    if (user) put('/api/profile/theme', { theme: value }).catch(() => undefined);
  };

  return (
    <button className="btn btn--quiet btn--icon" type="button" onClick={onClick} aria-label={`Cambiar a modo ${next}`} title={`Modo ${next}`}>
      <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
    </button>
  );
}
