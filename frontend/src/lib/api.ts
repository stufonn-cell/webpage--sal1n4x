/**
 * Cliente de la API. Todas las peticiones pasan por aqui:
 *  - envia la cookie de sesion (mismo origen) y el token CSRF en cabecera,
 *  - convierte cualquier fallo en un ApiError con un mensaje legible,
 *  - si el token CSRF expiro (419) lo renueva y reintenta una sola vez.
 *
 * Nunca se muestran al usuario detalles tecnicos: el backend ya responde con
 * mensajes pensados para personas.
 */

export type FieldErrors = Record<string, string>;

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly fields: FieldErrors = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

const FALLBACK_MESSAGES: Record<number, string> = {
  0: 'No pudimos conectar. Revisa tu conexión e inténtalo de nuevo.',
  401: 'Tu sesión terminó. Vuelve a ingresar para continuar.',
  403: 'No tienes permiso para ver esto.',
  404: 'No encontramos lo que buscas.',
  413: 'El archivo es demasiado grande.',
  419: 'Tu sesión de seguridad expiró. Recarga la página.',
  429: 'Hiciste varios intentos seguidos. Espera un momento.',
  500: 'Algo salió mal de nuestro lado. Intenta de nuevo en unos minutos.',
};

let csrfToken = '';
const unauthorizedListeners = new Set<() => void>();

export function setCsrfToken(token: string): void {
  csrfToken = token;
}

/** Permite a la sesion reaccionar cuando el backend responde 401. */
export function onUnauthorized(listener: () => void): () => void {
  unauthorizedListeners.add(listener);
  return () => unauthorizedListeners.delete(listener);
}

type Query = Record<string, string | number | boolean | null | undefined>;

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  query?: Query;
  signal?: AbortSignal;
}

function buildUrl(path: string, query?: Query): string {
  if (!query) return path;
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
  }
  const qs = params.toString();
  return qs ? `${path}?${qs}` : path;
}

async function send(path: string, options: RequestOptions, retried: boolean): Promise<unknown> {
  const method = options.method ?? 'GET';
  const headers: Record<string, string> = { Accept: 'application/json' };
  let body: BodyInit | undefined;

  if (options.body instanceof FormData) {
    body = options.body;
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }

  if (method !== 'GET') headers['X-CSRF-Token'] = csrfToken;

  let response: Response;
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      body,
      credentials: 'same-origin',
      signal: options.signal,
    });
  } catch (error) {
    if ((error as Error).name === 'AbortError') throw error;
    throw new ApiError(0, FALLBACK_MESSAGES[0]);
  }

  if (response.status === 419 && !retried) {
    await refreshCsrf();
    return send(path, options, true);
  }

  if (response.status === 204) return null;

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const error = (payload as { error?: { message?: string; fields?: FieldErrors } } | null)?.error;
    if (response.status === 401) unauthorizedListeners.forEach((listener) => listener());
    throw new ApiError(
      response.status,
      error?.message ?? FALLBACK_MESSAGES[response.status] ?? FALLBACK_MESSAGES[500],
      error?.fields ?? {},
    );
  }

  return payload;
}

async function refreshCsrf(): Promise<void> {
  try {
    const response = await fetch('/api/session', { credentials: 'same-origin' });
    const payload = (await response.json()) as { data?: { csrfToken?: string } };
    if (payload.data?.csrfToken) setCsrfToken(payload.data.csrfToken);
  } catch {
    // Si falla, el reintento devolvera el error original.
  }
}

/** Peticion que devuelve la respuesta completa ({ data, message }). */
export function request<T = unknown>(path: string, options: RequestOptions = {}): Promise<T> {
  return send(path, options, false) as Promise<T>;
}

/** Atajo para lecturas: devuelve solo `data`. */
export async function get<T>(path: string, query?: Query, signal?: AbortSignal): Promise<T> {
  const payload = await request<{ data: T }>(path, { query, signal });
  return payload.data;
}

export interface MutationResult<T = unknown> {
  data: T;
  message?: string;
}

export function post<T = unknown>(path: string, body?: unknown): Promise<MutationResult<T>> {
  return request<MutationResult<T>>(path, { method: 'POST', body });
}

export function put<T = unknown>(path: string, body?: unknown): Promise<MutationResult<T>> {
  return request<MutationResult<T>>(path, { method: 'PUT', body });
}

export function patch<T = unknown>(path: string, body?: unknown): Promise<MutationResult<T>> {
  return request<MutationResult<T>>(path, { method: 'PATCH', body });
}

export function del<T = unknown>(path: string): Promise<MutationResult<T>> {
  return request<MutationResult<T>>(path, { method: 'DELETE' });
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.message;
  return FALLBACK_MESSAGES[500];
}
