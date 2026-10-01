import { put } from '@/lib/api';
import { useSession } from '@/session/SessionProvider';
import { useTheme } from '@/session/ThemeProvider';
import { Icon } from './Icon';

export function ThemeToggle() {
  const { theme, toggle } = useTheme();
  const { user } = useSession();
  const next = theme === 'dark' ? 'light' : 'dark';

  const onClick = () => {
    const value = toggle();
    // When signed in, the preference travels with the account to other devices.
    if (user) put('/api/profile/theme', { theme: value }).catch(() => undefined);
  };

  return (
    <button className="btn btn--quiet btn--icon" type="button" onClick={onClick} aria-label={`Switch to ${next} mode`} title={next === 'light' ? 'Light mode' : 'Dark mode'}>
      <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
    </button>
  );
}
