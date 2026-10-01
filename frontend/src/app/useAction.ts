import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { useToast } from '@/components/ui/Feedback';
import type { MutationResult } from '@/lib/api';

interface Options<TResult> {
  /** Queries to refresh after the action. */
  invalidate?: QueryKey[];
  /** Message used when the backend does not return its own. */
  success?: string;
  onSuccess?: (result: MutationResult<TResult>) => void;
}

/**
 * One-off action (sign, change status, delete...) with a success or error
 * toast and a refresh of the affected queries.
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
      toast.success(result.message ?? success ?? 'Done.');
      await Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
      onSuccess?.(result);
    },
    onError: (error) => toast.error(error),
  });

  return { run: mutation.mutateAsync, pending: mutation.isPending };
}

/** Refreshes queries after a form is saved (forms handle their own errors). */
export function useInvalidate() {
  const queryClient = useQueryClient();
  return (...keys: QueryKey[]) => Promise.all(keys.map((queryKey) => queryClient.invalidateQueries({ queryKey })));
}
