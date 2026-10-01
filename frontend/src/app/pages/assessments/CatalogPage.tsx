import { useQuery } from '@tanstack/react-query';
import { ButtonLink } from '@/components/ui/Button';
import { Badge, PageHeader, Panel } from '@/components/ui/Display';
import { QueryState } from '@/components/ui/Feedback';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { get } from '@/lib/api';
import { severityTone } from '@/lib/meta';
import type { Instrument } from '@/lib/types';

export default function CatalogPage() {
  useDocumentTitle('Instrument catalog');
  const query = useQuery({ queryKey: ['instruments'], queryFn: () => get<Instrument[]>('/api/instruments'), staleTime: Infinity });

  return (
    <>
      <PageHeader
        back={{ to: '/app/assessments', label: 'Assessments' }}
        title="Instrument catalog"
        subtitle="Free-to-use scales with automatic scoring. Administering and interpreting them requires professional training."
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
                <ButtonLink to={`/app/assessments/new?instrument=${instrument.code}`} size="sm">
                  Administer
                </ButtonLink>
              }
            >
              <div className="stack">
                <p className="small soft">{instrument.description}</p>
                <p className="xsmall muted">
                  {instrument.items.length} items · range 0 to {instrument.maxScore} · {instrument.window}
                  {instrument.subscales.length > 0 && ` · subscales: ${instrument.subscales.join(', ')}`}
                </p>
                <div className="cluster">
                  {instrument.bands.map((band) => (
                    <Badge key={band.label} tone={severityTone(band.label)}>
                      {band.label} {band.min}–{band.max}
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
