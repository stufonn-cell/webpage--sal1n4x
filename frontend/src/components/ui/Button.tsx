import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { Link, type LinkProps } from 'react-router';
import { Icon, type IconName } from './Icon';

type Variant = 'default' | 'primary' | 'quiet' | 'danger';
type Size = 'sm' | 'md' | 'lg';

interface Common {
  variant?: Variant;
  size?: Size;
  icon?: IconName;
  iconRight?: IconName;
  block?: boolean;
  iconOnly?: boolean;
  children?: ReactNode;
}

function classes({ variant = 'default', size = 'md', block, iconOnly }: Common, extra?: string, loading?: boolean) {
  return [
    'btn',
    variant !== 'default' && `btn--${variant}`,
    size !== 'md' && `btn--${size}`,
    block && 'btn--block',
    iconOnly && 'btn--icon',
    loading && 'is-loading',
    extra,
  ]
    .filter(Boolean)
    .join(' ');
}

function Content({ icon, iconRight, children, size }: Common) {
  const iconSize = size === 'sm' ? 15 : 17;
  return (
    <>
      {icon && <Icon name={icon} size={iconSize} />}
      {children !== undefined && <span className="btn__label">{children}</span>}
      {iconRight && <Icon name={iconRight} size={iconSize} />}
    </>
  );
}

interface ButtonProps extends Common, Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> {
  loading?: boolean;
  loadingLabel?: string;
}

export function Button({
  variant,
  size,
  icon,
  iconRight,
  block,
  iconOnly,
  loading = false,
  loadingLabel = 'Processing',
  className,
  children,
  type = 'button',
  disabled,
  ...rest
}: ButtonProps) {
  const common = { variant, size, block, iconOnly };
  return (
    <button
      type={type}
      className={classes(common, className, loading)}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...rest}
    >
      <Content icon={loading ? undefined : icon} iconRight={iconRight} size={size}>
        {children}
      </Content>
      {loading && (
        <span className="btn__spinner" aria-hidden="true">
          <span className="spinner" />
        </span>
      )}
      {loading && <span className="visually-hidden">{loadingLabel}</span>}
    </button>
  );
}

interface ButtonLinkProps extends Common, Omit<LinkProps, 'children'> {}

export function ButtonLink({ variant, size, icon, iconRight, block, iconOnly, className, children, ...rest }: ButtonLinkProps) {
  return (
    <Link className={classes({ variant, size, block, iconOnly }, className)} {...rest}>
      <Content icon={icon} iconRight={iconRight} size={size}>
        {children}
      </Content>
    </Link>
  );
}
