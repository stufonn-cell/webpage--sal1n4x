import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Alert, Badge, Panel } from '@/components/ui/Display';
import { FormAlert, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { ApiError, get, post } from '@/lib/api';
import { formatDateTime, fullName } from '@/lib/format';
import type { Consent } from '@/lib/types';
import { SignaturePad, type Stroke } from './SignaturePad';

/** La firma se muestra como imagen: un <img> nunca ejecuta codigo del SVG. */
function SignatureImage({ svg }: { svg: string }) {
  if (!svg) return null;
  return <img className="signature-image" src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`} alt="Firma registrada" />;
}

interface ConsentDocumentProps {
  id: string;
  audience: 'staff' | 'patient';
  onSigned?: () => void;
}

export function ConsentDocument({ id, audience, onSigned }: ConsentDocumentProps) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const query = useQuery({ queryKey: ['consent', id], queryFn: () => get<Consent & { body: string }>(`/api/consents/${id}`) });
  const consent = query.data;

  const [name, setName] = useState('');
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [signing, setSigning] = useState(false);

  const signedName = name || (consent ? fullName(consent) : '');

  const sign = async () => {
    const nextErrors: Record<string, string> = {};
    if (!signedName.trim()) nextErrors.signed_name = 'Escribe el nombre completo.';
    if (strokes.length === 0) nextErrors.strokes = 'Dibuja la firma en el recuadro.';
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;

    setSigning(true);
    setFormError('');
    try {
      const result = await post(`/api/consents/${id}/sign`, { signed_name: signedName.trim(), strokes });
      toast.success(result.message ?? 'Consentimiento firmado.');
      await queryClient.invalidateQueries({ queryKey: ['consent', id] });
      await queryClient.invalidateQueries({ queryKey: ['consents'] });
      await queryClient.invalidateQueries({ queryKey: ['portal'] });
      onSigned?.();
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fields);
        setFormError(error.message);
      }
    } finally {
      setSigning(false);
    }
  };

  return (
    <QueryState isPending={query.isPending} error={query.error} onRetry={query.refetch}>
      {consent && (
        <div className="section-gap consent">
          <div className="split">
            <p className="soft small">
              {fullName(consent)} · {consent.record_number}
            </p>
            <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>{consent.status === 'signed' ? 'Firmado' : 'Pendiente de firma'}</Badge>
          </div>

          <Panel>
            <div className="consent__body">
              {consent.body.split('\n\n').map((paragraph, index) => (
                <p key={index}>{paragraph}</p>
              ))}
            </div>
          </Panel>

          {consent.status === 'signed' ? (
            <Panel title="Firma registrada" titleId="firma">
              <div className="stack">
                <p>
                  <strong>{consent.signed_name}</strong>
                </p>
                <p className="xsmall muted">
                  Firmado el {formatDateTime(consent.signed_at)}
                  {audience === 'staff' && consent.signed_ip && ` · desde ${consent.signed_ip}`}
                </p>
                <SignatureImage svg={consent.signature_svg} />
              </div>
            </Panel>
          ) : (
            <Panel title={audience === 'patient' ? 'Tu firma' : 'Firmar con el paciente'} titleId="firmar">
              <div className="inline-form">
                {audience === 'patient' && (
                  <Alert tone="info">Tómate el tiempo que necesites para leerlo. Si algo no está claro, pregúntale a tu profesional antes de firmar.</Alert>
                )}
                <FormAlert message={formError} />
                <TextField
                  label="Nombre completo"
                  id="signed_name"
                  value={signedName}
                  onChange={(event) => setName(event.target.value)}
                  error={errors.signed_name}
                  autoComplete="name"
                />
                <div className="field">
                  <span className="field__label">Firma</span>
                  <SignaturePad onChange={setStrokes} error={errors.strokes} />
                </div>
                <div>
                  <Button variant="primary" icon="pen" onClick={sign} loading={signing} loadingLabel="Registrando la firma">
                    Firmar consentimiento
                  </Button>
                </div>
              </div>
            </Panel>
          )}
        </div>
      )}
    </QueryState>
  );
}
