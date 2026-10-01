import { useId, useState, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { Icon } from './Icon';
import type { Option } from '@/lib/types';

/**
 * Campo con etiqueta, ayuda y error asociados por aria-describedby. El error
 * se anuncia a lectores de pantalla y marca el control con aria-invalid.
 */

interface WrapperProps {
  id: string;
  label: ReactNode;
  hint?: ReactNode;
  error?: string;
  optional?: boolean;
  className?: string;
  children: (describedBy: string | undefined) => ReactNode;
}

function FieldWrapper({ id, label, hint, error, optional, className, children }: WrapperProps) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div className={['field', error && 'has-error', className].filter(Boolean).join(' ')}>
      <label className="field__label" htmlFor={id}>
        {label}
        {optional && <span className="field__optional"> (opcional)</span>}
      </label>
      {children(describedBy)}
      {hint && !error && (
        <span className="field__hint" id={hintId}>
          {hint}
        </span>
      )}
      {error && (
        <span className="field__error" id={errorId} role="alert">
          <Icon name="alert" size={14} />
          {error}
        </span>
      )}
    </div>
  );
}

type Base = { label: ReactNode; hint?: ReactNode; error?: string; optional?: boolean; wrapperClassName?: string };

export function TextField({
  label,
  hint,
  error,
  optional,
  wrapperClassName,
  id,
  ...rest
}: Base & InputHTMLAttributes<HTMLInputElement>) {
  const fallback = useId();
  const inputId = id ?? fallback;
  return (
    <FieldWrapper id={inputId} label={label} hint={hint} error={error} optional={optional} className={wrapperClassName}>
      {(describedBy) => (
        <input
          id={inputId}
          className="input"
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...rest}
        />
      )}
    </FieldWrapper>
  );
}

export function PasswordField(props: Base & Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>) {
  const [visible, setVisible] = useState(false);
  const { label, hint, error, optional, wrapperClassName, id, ...rest } = props;
  const fallback = useId();
  const inputId = id ?? fallback;

  return (
    <FieldWrapper id={inputId} label={label} hint={hint} error={error} optional={optional} className={wrapperClassName}>
      {(describedBy) => (
        <div className="input-group">
          <input
            id={inputId}
            className="input"
            type={visible ? 'text' : 'password'}
            aria-invalid={error ? true : undefined}
            aria-describedby={describedBy}
            {...rest}
          />
          <button
            type="button"
            className="btn btn--quiet btn--icon btn--sm input-group__action"
            onClick={() => setVisible((value) => !value)}
            aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            aria-pressed={visible}
          >
            <Icon name={visible ? 'eyeOff' : 'eye'} size={17} />
          </button>
        </div>
      )}
    </FieldWrapper>
  );
}

interface SelectFieldProps extends Base, SelectHTMLAttributes<HTMLSelectElement> {
  options: Option[];
  placeholder?: string;
}

export function SelectField({
  label,
  hint,
  error,
  optional,
  wrapperClassName,
  options,
  placeholder,
  id,
  ...rest
}: SelectFieldProps) {
  const fallback = useId();
  const inputId = id ?? fallback;
  return (
    <FieldWrapper id={inputId} label={label} hint={hint} error={error} optional={optional} className={wrapperClassName}>
      {(describedBy) => (
        <select
          id={inputId}
          className="select"
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...rest}
        >
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      )}
    </FieldWrapper>
  );
}

export function TextAreaField({
  label,
  hint,
  error,
  optional,
  wrapperClassName,
  id,
  ...rest
}: Base & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const fallback = useId();
  const inputId = id ?? fallback;
  return (
    <FieldWrapper id={inputId} label={label} hint={hint} error={error} optional={optional} className={wrapperClassName}>
      {(describedBy) => (
        <textarea
          id={inputId}
          className="textarea"
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...rest}
        />
      )}
    </FieldWrapper>
  );
}

export function FormAlert({ message }: { message: string }) {
  if (!message) return null;
  return (
    <div className="alert alert--error" role="alert">
      <Icon name="alert" size={17} />
      <p>{message}</p>
    </div>
  );
}
