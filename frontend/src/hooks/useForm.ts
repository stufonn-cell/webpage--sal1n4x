import { useCallback, useState, type ChangeEvent, type FormEvent } from 'react';
import { ApiError, type FieldErrors } from '@/lib/api';

type Values = Record<string, unknown>;

interface Options<T extends Values> {
  initial: T;
  validate?: (values: T) => FieldErrors;
  onSubmit: (values: T) => Promise<void>;
}

/**
 * Dependency-free form state: values, per-field errors (from the client and
 * those the API returns with 422) and submitting state.
 */
export function useForm<T extends Values>({ initial, validate, onSubmit }: Options<T>) {
  const [values, setValues] = useState<T>(initial);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const set = useCallback(<K extends keyof T>(name: K, value: T[K]) => {
    setValues((current) => ({ ...current, [name]: value }));
    setErrors((current) => {
      if (!current[name as string]) return current;
      const next = { ...current };
      delete next[name as string];
      return next;
    });
  }, []);

  const bind = useCallback(
    (name: keyof T & string) => ({
      name,
      id: name,
      value: (values[name] ?? '') as string,
      onChange: (event: ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        set(name, event.target.value as T[typeof name]),
      error: errors[name],
    }),
    [errors, set, values],
  );

  const handleSubmit = useCallback(
    async (event?: FormEvent) => {
      event?.preventDefault();
      setFormError('');

      const clientErrors = validate?.(values) ?? {};
      if (Object.keys(clientErrors).length > 0) {
        setErrors(clientErrors);
        focusFirstError(clientErrors);
        return;
      }

      setSubmitting(true);
      try {
        await onSubmit(values);
      } catch (error) {
        if (error instanceof ApiError) {
          setErrors(error.fields);
          setFormError(error.message);
          focusFirstError(error.fields);
        } else {
          setFormError('Something went wrong on our side. Please try again in a few minutes.');
        }
      } finally {
        setSubmitting(false);
      }
    },
    [onSubmit, validate, values],
  );

  return { values, setValues, set, bind, errors, setErrors, formError, setFormError, submitting, handleSubmit };
}

function focusFirstError(errors: FieldErrors): void {
  const first = Object.keys(errors)[0];
  if (!first) return;
  window.requestAnimationFrame(() => {
    const element = document.getElementById(first);
    element?.focus({ preventScroll: false });
  });
}
