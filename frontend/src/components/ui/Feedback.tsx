import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { errorMessage } from '@/lib/api';
import { Button } from './Button';
import { EmptyState } from './Display';
import { Icon } from './Icon';

/* --- Avisos -------------------------------------------------------- */

type ToastTone = 'success' | 'error' | 'info';

interface Toast {
  id: number;
  tone: ToastTone;
  message: string;
  leaving?: boolean;
}

interface ToastApi {
  success: (message: string) => void;
  error: (message: string | unknown) => void;
  info: (message: string) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextId = useRef(1);

  const dismiss = useCallback((id: number) => {
    setToasts((items) => items.map((item) => (item.id === id ? { ...item, leaving: true } : item)));
    window.setTimeout(() => setToasts((items) => items.filter((item) => item.id !== id)), 260);
  }, []);

  const push = useCallback(
    (tone: ToastTone, message: string) => {
      const id = nextId.current++;
      setToasts((items) => [...items.slice(-2), { id, tone, message }]);
      window.setTimeout(() => dismiss(id), tone === 'error' ? 7000 : 4500);
    },
    [dismiss],
  );

  const api = useMemo<ToastApi>(
    () => ({
      success: (message) => push('success', message),
      error: (message) => push('error', typeof message === 'string' ? message : errorMessage(message)),
      info: (message) => push('info', message),
    }),
    [push],
  );

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="toasts" aria-live="polite" aria-relevant="additions">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            className={`toast toast--${toast.tone}${toast.leaving ? ' is-leaving' : ''}`}
            role={toast.tone === 'error' ? 'alert' : 'status'}
          >
            <Icon name={toast.tone === 'error' ? 'alert' : toast.tone === 'success' ? 'checkCircle' : 'info'} size={18} />
            <p className="toast__text">{toast.message}</p>
            <button className="toast__close" type="button" onClick={() => dismiss(toast.id)} aria-label="Cerrar aviso">
              <Icon name="close" size={16} />
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useToast debe usarse dentro de ToastProvider');
  return context;
}

/* --- Confirmacion -------------------------------------------------- */

interface ConfirmOptions {
  title: string;
  text?: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
}

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

/**
 * Dialogo de confirmacion basado en <dialog>: el navegador gestiona el foco
 * atrapado, Escape y el fondo inerte. Reemplaza a window.confirm.
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const resolver = useRef<((value: boolean) => void) | null>(null);
  const [options, setOptions] = useState<ConfirmOptions | null>(null);

  const confirm = useCallback<ConfirmFn>((next) => {
    setOptions(next);
    return new Promise<boolean>((resolve) => {
      resolver.current = resolve;
    });
  }, []);

  useEffect(() => {
    if (options && dialogRef.current && !dialogRef.current.open) dialogRef.current.showModal();
  }, [options]);

  const close = (value: boolean) => {
    dialogRef.current?.close();
    resolver.current?.(value);
    resolver.current = null;
    setOptions(null);
  };

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <dialog
        ref={dialogRef}
        className="dialog"
        aria-labelledby="confirm-title"
        onCancel={(event) => {
          event.preventDefault();
          close(false);
        }}
      >
        {options && (
          <>
            <div className="dialog__body">
              <h2 className="dialog__title" id="confirm-title">
                {options.title}
              </h2>
              {options.text && <div className="dialog__text">{options.text}</div>}
            </div>
            <div className="dialog__actions">
              <Button onClick={() => close(false)} autoFocus>
                {options.cancelLabel ?? 'Cancelar'}
              </Button>
              <Button variant={options.danger ? 'danger' : 'primary'} onClick={() => close(true)}>
                {options.confirmLabel ?? 'Confirmar'}
              </Button>
            </div>
          </>
        )}
      </dialog>
    </ConfirmContext.Provider>
  );
}

export function useConfirm(): ConfirmFn {
  const context = useContext(ConfirmContext);
  if (!context) throw new Error('useConfirm debe usarse dentro de ConfirmProvider');
  return context;
}

/* --- Estados de carga ---------------------------------------------- */

export function LoadingLine({ label = 'Cargando' }: { label?: string }) {
  return (
    <div className="loading-line" role="status">
      <span className="spinner" aria-hidden="true" />
      <span>{label}…</span>
    </div>
  );
}

export function SkeletonRows({ rows = 4 }: { rows?: number }) {
  return (
    <div className="loading-block" aria-hidden="true">
      {Array.from({ length: rows }, (_, index) => (
        <span key={index} className="skeleton" style={{ width: `${92 - ((index * 17) % 40)}%` }} />
      ))}
    </div>
  );
}

interface QueryStateProps {
  isPending: boolean;
  error: unknown;
  onRetry?: () => void;
  skeleton?: ReactNode;
  children: ReactNode;
}

/** Envoltorio comun para cualquier pantalla que depende de una consulta. */
export function QueryState({ isPending, error, onRetry, skeleton, children }: QueryStateProps) {
  if (isPending) return <>{skeleton ?? <SkeletonRows />}</>;
  if (error) {
    return (
      <EmptyState
        icon="alert"
        title="No pudimos cargar esta información"
        text={errorMessage(error)}
        action={
          onRetry && (
            <Button size="sm" onClick={onRetry}>
              Intentar de nuevo
            </Button>
          )
        }
      />
    );
  }
  return <>{children}</>;
}

interface PaginationProps {
  page: number;
  pages: number;
  total: number;
  onChange: (page: number) => void;
}

export function Pagination({ page, pages, total, onChange }: PaginationProps) {
  if (pages <= 1) return null;
  return (
    <nav className="pagination" aria-label="Paginación">
      <span>
        Página {page} de {pages} · {total} en total
      </span>
      <div className="cluster">
        <Button size="sm" icon="chevronLeft" disabled={page <= 1} onClick={() => onChange(page - 1)}>
          Anterior
        </Button>
        <Button size="sm" iconRight="chevronRight" disabled={page >= pages} onClick={() => onChange(page + 1)}>
          Siguiente
        </Button>
      </div>
    </nav>
  );
}
