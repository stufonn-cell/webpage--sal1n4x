import { useQuery } from '@tanstack/react-query';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { useNavigate } from 'react-router';
import { Icon } from '@/components/ui/Icon';
import { useDebounced } from '@/hooks/useDebounced';
import { request } from '@/lib/api';

interface Result {
  group: string;
  label: string;
  meta: string;
  url: string;
}

/**
 * Busqueda global (Ctrl/Cmd + K) con patron combobox: flechas para moverse,
 * Enter para abrir, Escape para cerrar. Los resultados se pintan como texto,
 * nunca como HTML.
 */
export function CommandSearch() {
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const listId = useId();
  const [term, setTerm] = useState('');
  const [open, setOpen] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const debounced = useDebounced(term.trim(), 220);

  const { data = [], isFetching } = useQuery({
    queryKey: ['search', debounced],
    queryFn: ({ signal }) =>
      request<{ results: Result[] }>('/api/search', { query: { q: debounced }, signal }).then((payload) => payload.results),
    enabled: debounced.length >= 2,
    staleTime: 30_000,
  });

  useEffect(() => {
    const onKey = (event: globalThis.KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        inputRef.current?.focus();
        inputRef.current?.select();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => setHighlight(0), [data]);

  const go = (result: Result) => {
    setOpen(false);
    setTerm('');
    inputRef.current?.blur();
    navigate(result.url);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setOpen(true);
      setHighlight((index) => Math.min(index + 1, data.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setHighlight((index) => Math.max(index - 1, 0));
    } else if (event.key === 'Enter' && data[highlight]) {
      event.preventDefault();
      go(data[highlight]);
    } else if (event.key === 'Escape') {
      setOpen(false);
      inputRef.current?.blur();
    }
  };

  const showPanel = open && debounced.length >= 2;

  return (
    <div className="command" onBlur={(event) => !event.currentTarget.contains(event.relatedTarget) && setOpen(false)}>
      <Icon name="search" size={17} className="command__icon" />
      <input
        ref={inputRef}
        className="command__input"
        type="search"
        placeholder="Buscar pacientes o notas…"
        aria-label="Buscar pacientes o notas"
        role="combobox"
        aria-expanded={showPanel}
        aria-controls={listId}
        aria-autocomplete="list"
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
      <kbd className="command__hint" aria-hidden="true">
        Ctrl K
      </kbd>

      {showPanel && (
        <div className="command__panel">
          {data.length === 0 ? (
            <p className="command__empty">{isFetching ? 'Buscando…' : `Nada coincide con “${debounced}”.`}</p>
          ) : (
            <ul id={listId} role="listbox" aria-label="Resultados">
              {data.map((result, index) => (
                <li key={`${result.url}-${index}`} role="presentation">
                  {(index === 0 || data[index - 1].group !== result.group) && (
                    <p className="command__group" role="presentation">
                      {result.group}
                    </p>
                  )}
                  <button
                    id={`${listId}-${index}`}
                    type="button"
                    role="option"
                    aria-selected={index === highlight}
                    className="command__option"
                    onMouseEnter={() => setHighlight(index)}
                    onClick={() => go(result)}
                    tabIndex={-1}
                  >
                    <span>{result.label}</span>
                    <span className="command__meta">{result.meta}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
