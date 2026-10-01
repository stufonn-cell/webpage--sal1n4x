import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { useToast } from '@/components/ui/Feedback';
import type { MutationResult } from '@/lib/api';

interface Options<TResult> {
  /** Consultas que deben refrescarse tras la accion. */
  invalidate?: QueryKey[];
  /** Mensaje si el backend no devuelve uno propio. */
  success?: string;
  onSuccess?: (result: MutationResult<TResult>) => void;
}

/**
 * Accion puntual (firmar, cambiar estado, eliminar...) con aviso de exito o
 * error y refresco de las consultas afectadas.
 */
export function useAction<TVars, TResult = unknown>(
  action: (vars: TVars) => Promise<MutationResult<TResult>>,
  { invalidate = [], success, onSuccess }: Options<TResult> = {},
) {
  const queryClient = useQueryClient();
  const toast = useToast();

  const mutation = useMutation({
    mutationFn: action,
    onSuccess: async (result) => {
      toast.success(result.message ?? success ?? 'Listo.');
      await Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
      onSuccess?.(result);
    },
    onError: (error) => toast.error(error),
  });

  return { run: mutation.mutateAsync, pending: mutation.isPending };
}

/** Refresca consultas tras guardar un formulario (los formularios gestionan sus errores). */
export function useInvalidate() {
  const queryClient = useQueryClient();
  return (...keys: QueryKey[]) => Promise.all(keys.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
}
