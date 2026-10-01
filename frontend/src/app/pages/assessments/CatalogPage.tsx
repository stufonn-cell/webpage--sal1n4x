import { useQuery } from '@tanstack/react-query';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { pretty } from '@/lib/format';
import { severityTone } from '@/lib/meta';
import type { Instrument } from '@/lib/types';

export default function CatalogPage() {
  useDocumentTitle('Catálogo de instrumentos');
  const query = useQuery({ queryKey: ['instruments'], queryFn: () => get<Instrument[]>('/api/instruments'), staleTime: Infinity });

  return (
    <>
      <PageHeader
        back={{ to: '/app/evaluaciones', label: 'Evaluaciones' }}
        title="Catálogo de instrumentos"
        subtitle="Escalas de uso libre con corrección automática. Su aplicación e interpretación requieren formación profesional."
      />
      <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
        <div className="grid-2">
          {query.data?.map((instrument) => (
            <Panel
              key={instrument.code}
              title={`${instrument.code} · ${instrument.domain}`}
              subtitle={instrument.name}
              titleId={`inst-${instrument.code}`}
              actions={
                <ButtonLink to={`/app/evaluaciones/nueva?instrumento=${instrument.code}`} size="sm">
                  Aplicar
                </ButtonLink>
              }
            >
              <div className="stack">
                <p className="small soft">{instrument.description}</p>
                <p className="xsmall muted">
                  {instrument.items.length} ítems · rango 0 a {instrument.maxScore} · {instrument.window}
                  {instrument.subscales.length > 0 && ` · subescalas: ${instrument.subscales.map(pretty).join(', ')}`}
                </p>
                <div className="cluster">
                  {instrument.bands.map((band) => (
                    <Badge key={band.label} tone={severityTone(band.label)}>
                      {pretty(band.label)} {band.min}–{band.max}
                    </Badge>
                  ))}
                </div>
              </div>
            </Panel>
          ))}
        </div>
      </QueryState>
    </>
  );
}
