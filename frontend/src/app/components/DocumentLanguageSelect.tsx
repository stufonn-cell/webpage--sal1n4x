import { useSearchParams } from 'react-router';
import { isDocumentLanguage } from '@/lib/documentText';
import { useMeta } from '@/lib/meta';
import type { DocumentLanguage } from '@/lib/types';

/**
 * Language of the document shown on a page (printed notes, invoices and
 * assessment reports). It lives in the URL (?lang=es) so a printed copy or a
 * shared link keeps it; otherwise the practice default from Settings applies.
 */
export function useDocumentLanguage(): [DocumentLanguage, (language: DocumentLanguage) => void] {
  const { data: meta } = useMeta();
  const [params, setParams] = useSearchParams();
  const requested = params.get('lang');
  const language: DocumentLanguage = isDocumentLanguage(requested) ? requested : (meta?.settings.document_language ?? 'en');

  const change = (next: DocumentLanguage) => {
    const updated = new URLSearchParams(params);
    updated.set('lang', next);
    setParams(updated, { replace: true });
  };

  return [language, change];
}

export function DocumentLanguageSelect({ value, onChange }: { value: DocumentLanguage; onChange: (language: DocumentLanguage) => void }) {
  const { data: meta } = useMeta();
  const options = meta?.documentLanguages ?? [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish (Español)' },
  ];

  return (
    <label className="doc-language no-print">
      <span className="doc-language__label">Document language</span>
      <select
        className="select select--sm"
        value={value}
        onChange={(event) => isDocumentLanguage(event.target.value) && onChange(event.target.value)}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}
