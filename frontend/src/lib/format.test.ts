import { describe, expect, it } from 'vitest';
import { ageFrom, formatBytes, fullName, greeting, initials, parseDate, pluralize, pretty, toISODate } from './format';
import { labelOf, severityTone } from './meta';

describe('fechas', () => {
  it('interpreta fechas y fechas con hora de MySQL', () => {
    expect(parseDate('2026-10-01')?.getDate()).toBe(1);
    expect(parseDate('2026-10-01 14:30:00')?.getHours()).toBe(14);
  });

  it('descarta valores vacios o nulos de MySQL', () => {
    expect(parseDate('')).toBeNull();
    expect(parseDate('0000-00-00')).toBeNull();
    expect(parseDate(null)).toBeNull();
  });

  it('formatea a ISO sin desplazar por la zona horaria', () => {
    expect(toISODate(new Date(2026, 0, 5, 23, 30))).toBe('2026-01-05');
  });

  it('calcula la edad en anos cumplidos', () => {
    const today = new Date();
    const birth = `${today.getFullYear() - 30}-01-01`;
    expect(ageFrom(birth)).toBe(30);
    expect(ageFrom(null)).toBeNull();
  });

  it('saluda segun la hora del dia', () => {
    expect(greeting(new Date(2026, 0, 1, 8))).toBe('Buenos días');
    expect(greeting(new Date(2026, 0, 1, 15))).toBe('Buenas tardes');
    expect(greeting(new Date(2026, 0, 1, 21))).toBe('Buenas noches');
  });
});

describe('texto', () => {
  it('toma las iniciales de las dos primeras palabras', () => {
    expect(initials('laura moreno díaz')).toBe('LM');
    expect(initials('')).toBe('');
  });

  it('compone el nombre completo sin espacios sobrantes', () => {
    expect(fullName({ first_name: 'Ana', last_name: null })).toBe('Ana');
  });

  it('pluraliza segun la cantidad', () => {
    expect(pluralize(1, 'cita')).toBe('1 cita');
    expect(pluralize(3, 'cita')).toBe('3 citas');
  });

  it('formatea tamanos de archivo', () => {
    expect(formatBytes(500)).toBe('500 B');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
  });

  it('agrega tildes a etiquetas historicas solo al mostrarlas', () => {
    expect(pretty('Minima')).toBe('Mínima');
    expect(pretty('Sintomatologia minima. Seguimiento de rutina.')).toBe('Sintomatología mínima. Seguimiento de rutina.');
    expect(pretty('Estres percibido alto')).toBe('Estrés percibido alto');
    expect(pretty(null)).toBe('');
  });
});

describe('catalogos', () => {
  it('devuelve la etiqueta o el valor si no existe', () => {
    const options = [{ value: 'online', label: 'Virtual' }];
    expect(labelOf(options, 'online')).toBe('Virtual');
    expect(labelOf(options, 'otro')).toBe('otro');
    expect(labelOf(options, null)).toBe('—');
  });

  it('asigna un tono a cada severidad', () => {
    expect(severityTone('Severa')).toBe('danger');
    expect(severityTone('Moderada-severa')).toBe('danger');
    expect(severityTone('Moderada')).toBe('warning');
    expect(severityTone('Leve')).toBe('info');
    expect(severityTone('Minima')).toBe('success');
  });
});
