import { useQuery } from '@tanstack/react-query';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Icon } from '@/components/ui/Icon';
import { useDebounced } from '@/hooks/useDebounced';
import { request } from '@/lib/api';
import type { Icd11Code } from '@/lib/types';

interface Props {
  id: string;
  label: string;
  value: Icd11Code | null;
  onChange: (code: Icd11Code | null) => void;
  error?: string;
  release?: string;
}

/**
 * ICD-11 lookup by code or by words of the title, with the combobox pattern:
 * arrows to move, Enter to pick, Escape to close. Mental health codes
 * (chapter 06) are listed first by the API.
 */
export function Icd11Picker({ id, label, value, onChange, error, release }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const listId = useId();
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;
  const [term, setTerm] = useState('');
  const [open, setOpen] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const debounced = useDebounced(term.trim(), 220);

  const { data = [], isFetching, isError } = useQuery({
    queryKey: ['icd11', debounced],
    queryFn: ({ signal }) =>
      request<{ data: { results: Icd11Code[] } }>('/api/icd11', { query: { q: debounced, limit: 25 }, signal }).then(
        (payload) => payload.data.results,
      ),
    enabled: debounced.length >= 2,
    staleTime: 5 * 60_000,
  });

  useEffect(() => setHighlight(0), [data]);

  const pick = (code: Icd11Code) => {
    onChange(code);
    setTerm('');
    setOpen(false);
  };

  const clear = () => {
    onChange(null);
    window.requestAnimationFrame(() => inputRef.current?.focus());
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setOpen(true);
      setHighlight((index) => Math.min(index + 1, data.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setHighlight((index) => Math.max(index - 1, 0));
    } else if (event.key === 'Enter') {
      // Never submit the surrounding form from the search box.
      event.preventDefault();
      if (open && data[highlight]) pick(data[highlight]);
    } else if (event.key === 'Escape') {
      setOpen(false);
    }
  };

  const showPanel = open && debounced.length >= 2;
  const describedBy = error ? errorId : hintId;

  if (value) {
    return (
      <div className={error ? 'field has-error' : 'field'}>
        <span className="field__label" id={`${id}-label`}>
          {label}
        </span>
        <div className="picker__chosen" role="group" aria-labelledby={`${id}-label`}>
          <span className="picker__code">{value.code}</span>
          <span className="picker__title">{value.title}</span>
          <Button size="sm" variant="quiet" icon="close" onClick={clear} aria-label={`Remove ${value.code} and search again`}>
            Change
          </Button>
        </div>
        {error && (
          <span className="field__error" id={errorId} role="alert">
            <Icon name="alert" size={14} />
            {error}
          </span>
        )}
      </div>
    );
  }

  return (
    <div
      className={error ? 'field picker has-error' : 'field picker'}
      onBlur={(event) => !event.currentTarget.contains(event.relatedTarget) && setOpen(false)}
    >
      <label className="field__label" htmlFor={id}>
        {label}
      </label>
      <div className="picker__box">
        <Icon name="search" size={16} className="picker__icon" />
        <input
          ref={inputRef}
          id={id}
          className="input picker__input"
          type="search"
          placeholder="Code or words, e.g. 6B00 or anxiety"
          role="combobox"
          aria-expanded={showPanel}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          aria-activedescendant={showPanel && data[highlight] ? `${listId}-${highlight}` : undefined}
          value={term}
          onChange={(event) => {
            setTerm(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          autoComplete="off"
        />
        {showPanel && (
          <div className="picker__panel">
            {data.length === 0 ? (
              <p className="picker__empty">
                {isFetching ? 'Searching…' : isError ? 'The search failed. Try again.' : `Nothing in ICD-11 matches “${debounced}”.`}
              </p>
            ) : (
              <ul id={listId} role="listbox" aria-label="ICD-11 codes">
                {data.map((code, index) => (
                  <li key={code.code} role="presentation">
                    <button
                      id={`${listId}-${index}`}
                      type="button"
                      role="option"
                      aria-selected={index === highlight}
                      className="picker__option"
                      onMouseEnter={() => setHighlight(index)}
                      onMouseDown={(event) => event.preventDefault()}
                      onClick={() => pick(code)}
                      tabIndex={-1}
                    >
                      <span className="picker__code">{code.code}</span>
                      <span className="picker__title">{code.title}</span>
                      <span className="picker__meta">{code.icd10_code ? `ICD-10 ${code.icd10_code}` : 'No ICD-10 match'}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
      {error ? (
        <span className="field__error" id={errorId} role="alert">
          <Icon name="alert" size={14} />
          {error}
        </span>
      ) : (
        <span className="field__hint" id={hintId}>
          WHO ICD-11 MMS {release ?? ''}. Mental health codes (chapter 06) are listed first.
        </span>
      )}
    </div>
  );
}
