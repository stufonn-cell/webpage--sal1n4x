import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Alert, Badge, Panel } from '@/components/ui/Display';
import { FormAlert, TextField } from '@/components/ui/Field';
import { QueryState, useToast } from '@/components/ui/Feedback';
import { ApiError, get, post } from '@/lib/api';
import { documentText } from '@/lib/documentText';
import { formatDateTime, fullName } from '@/lib/format';
import type { Consent } from '@/lib/types';
import { SignaturePad, type Stroke } from './SignaturePad';

/** The signature is shown as an image: an <img> never runs code from the SVG. */
function SignatureImage({ svg, alt }: { svg: string; alt: string }) {
  if (!svg) return null;
  return <img className="signature-image" src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`} alt={alt} />;
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
  // The whole document, signature block included, follows the language it was created in.
  const t = documentText(consent?.language).consent;
  const locale = documentText(consent?.language).locale;

  const [name, setName] = useState('');
  const [strokes, setStrokes] = useState<Stroke[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [signing, setSigning] = useState(false);

  const signedName = name || (consent ? fullName(consent) : '');

  const sign = async () => {
    const nextErrors: Record<string, string> = {};
    if (!signedName.trim()) nextErrors.signed_name = t.enterName;
    if (strokes.length === 0) nextErrors.strokes = t.drawSignature;
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;

    setSigning(true);
    setFormError('');
    try {
      const result = await post(`/api/consents/${id}/sign`, { signed_name: signedName.trim(), strokes });
      toast.success(consent?.language === 'es' ? t.done : (result.message ?? t.done));
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
        <div className="section-gap consent" lang={consent.language}>
          <div className="split">
            <p className="soft small">
              {fullName(consent)} · {consent.record_number}
            </p>
            <Badge tone={consent.status === 'signed' ? 'success' : 'warning'}>{consent.status === 'signed' ? t.signed : t.pending}</Badge>
          </div>

          <Panel>
            <div className="consent__body">
              {consent.body.split('\n\n').map((paragraph, index) => (
                <p key={index}>{paragraph}</p>
              ))}
            </div>
          </Panel>

          {consent.status === 'signed' ? (
            <Panel title={t.recordedSignature} titleId="signature-record">
              <div className="stack">
                <p>
                  <strong>{consent.signed_name}</strong>
                </p>
                <p className="xsmall muted">
                  {t.signedOn(formatDateTime(consent.signed_at, locale))}
                  {audience === 'staff' && consent.signed_ip && ` · ${t.from(consent.signed_ip)}`}
                </p>
                <SignatureImage svg={consent.signature_svg} alt={t.recordedSignature} />
              </div>
            </Panel>
          ) : (
            <Panel title={audience === 'patient' ? t.yourSignature : t.signWithPatient} titleId="sign-consent">
              <div className="inline-form">
                {audience === 'patient' && (
                  <Alert tone="info">{t.readCalmly}</Alert>
                )}
                <FormAlert message={formError} />
                <TextField
                  label={t.fullName}
                  id="signed_name"
                  value={signedName}
                  onChange={(event) => setName(event.target.value)}
                  error={errors.signed_name}
                  autoComplete="name"
                />
                <div className="field">
                  <span className="field__label">{t.signature}</span>
                  <SignaturePad onChange={setStrokes} error={errors.strokes} language={consent.language} />
                </div>
                <div>
                  <Button variant="primary" icon="pen" onClick={sign} loading={signing} loadingLabel={t.signing}>
                    {t.sign}
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
