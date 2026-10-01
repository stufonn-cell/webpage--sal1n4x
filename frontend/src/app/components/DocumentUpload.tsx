import { useRef, useState, type DragEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { FormAlert, SelectField, TextField } from '@/components/ui/Field';
import { useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { ApiError, request } from '@/lib/api';
import { formatBytes } from '@/lib/format';
import { useMeta } from '@/lib/meta';
import { useInvalidate } from '../useAction';

const ACCEPT = '.pdf,.png,.jpg,.jpeg,.txt,.doc,.docx';
const MAX_BYTES = 10 * 1024 * 1024;

/** Carga de documentos con arrastrar y soltar. Si hay paciente fijo no se pide. */
export function DocumentUpload({ patientId }: { patientId?: number }) {
  const { data: meta } = useMeta();
  const toast = useToast();
  const invalidate = useInvalidate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [patient, setPatient] = useState(patientId ? String(patientId) : '');
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('general');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [dragging, setDragging] = useState(false);
  const [sending, setSending] = useState(false);

  const pick = (selected: File | undefined) => {
    if (!selected) return;
    if (selected.size > MAX_BYTES) {
      setErrors({ document: `El archivo pesa ${formatBytes(selected.size)}. El máximo es 10 MB.` });
      return;
    }
    setErrors({});
    setFile(selected);
    if (!title) setTitle(selected.name.replace(/\.[^.]+$/, ''));
  };

  const onDrop = (event: DragEvent) => {
    event.preventDefault();
    setDragging(false);
    pick(event.dataTransfer.files[0]);
  };

  const submit = async () => {
    const nextErrors: Record<string, string> = {};
    if (!patient) nextErrors.patient_id = 'Elige un paciente.';
    if (!file) nextErrors.document = 'Elige un archivo.';
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0 || !file) return;

    const body = new FormData();
    body.set('patient_id', patient);
    body.set('title', title);
    body.set('category', category);
    body.set('document', file);

    setSending(true);
    setFormError('');
    try {
      const result = await request<{ message?: string }>('/api/documents', { method: 'POST', body });
      toast.success(result.message ?? 'Documento adjuntado.');
      setFile(null);
      setTitle('');
      if (inputRef.current) inputRef.current.value = '';
      await invalidate(['documents'], ['patient', patient]);
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fields);
        setFormError(error.message);
      }
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="inline-form">
      <FormAlert message={formError} />
      {!patientId && (
        <SelectField
          label="Paciente"
          id="patient_id"
          placeholder="Elige un paciente"
          value={patient}
          onChange={(event) => setPatient(event.target.value)}
          options={(meta?.patients ?? []).map((item) => ({ value: String(item.id), label: item.label }))}
          error={errors.patient_id}
        />
      )}

      <div
        className={['dropzone', dragging && 'is-dragging', errors.document && 'has-error'].filter(Boolean).join(' ')}
        onDragOver={(event) => {
          event.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
      >
        <Icon name="upload" size={20} />
        {file ? (
          <p>
            <strong>{file.name}</strong> <span className="muted">· {formatBytes(file.size)}</span>
          </p>
        ) : (
          <p>Arrastra un archivo aquí o</p>
        )}
        <label className="btn btn--sm" htmlFor="document">
          {file ? 'Cambiar archivo' : 'Elegir archivo'}
        </label>
        <input
          ref={inputRef}
          id="document"
          className="visually-hidden"
          type="file"
          accept={ACCEPT}
          onChange={(event) => pick(event.target.files?.[0])}
        />
        <span className="field__hint">PDF, imagen, texto o Word. Máximo 10 MB.</span>
        {errors.document && (
          <span className="field__error" role="alert">
            {errors.document}
          </span>
        )}
      </div>

      <TextField label="Título" id="doc-title" value={title} onChange={(event) => setTitle(event.target.value)} />
      <SelectField
        label="Categoría"
        id="doc-category"
        value={category}
        onChange={(event) => setCategory(event.target.value)}
        options={meta?.documentCategories ?? []}
      />
      <div>
        <Button variant="primary" icon="upload" onClick={submit} loading={sending} loadingLabel="Subiendo">
          Adjuntar documento
        </Button>
      </div>
    </div>
  );
}
