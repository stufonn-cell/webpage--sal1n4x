import { describe, expect, it } from 'vitest';
import { ageFrom, formatBytes, formatMoney, fullName, greeting, initials, parseDate, pluralize, relativeDay, toISODate } from './format';
import { labelOf, severityTone } from './meta';

describe('dates', () => {
  it('parses MySQL dates and date-times', () => {
    expect(parseDate('2026-10-01')?.getDate()).toBe(1);
    expect(parseDate('2026-10-01 14:30:00')?.getHours()).toBe(14);
  });

  it('ignores empty or null MySQL values', () => {
    expect(parseDate('')).toBeNull();
    expect(parseDate('0000-00-00')).toBeNull();
    expect(parseDate(null)).toBeNull();
  });

  it('formats to ISO without shifting by time zone', () => {
    expect(toISODate(new Date(2026, 0, 5, 23, 30))).toBe('2026-01-05');
  });

  it('computes age in completed years', () => {
    const today = new Date();
    const birth = `${today.getFullYear() - 30}-01-01`;
    expect(ageFrom(birth)).toBe(30);
    expect(ageFrom(null)).toBeNull();
  });

  it('greets according to the time of day', () => {
    expect(greeting(new Date(2026, 0, 1, 8))).toBe('Good morning');
    expect(greeting(new Date(2026, 0, 1, 15))).toBe('Good afternoon');
    expect(greeting(new Date(2026, 0, 1, 21))).toBe('Good evening');
  });

  it('names nearby days in plain words', () => {
    const day = (offset: number) => {
      const date = new Date();
      date.setDate(date.getDate() + offset);
      return toISODate(date);
    };
    expect(relativeDay(day(0))).toBe('Today');
    expect(relativeDay(day(1))).toBe('Tomorrow');
    expect(relativeDay(day(-1))).toBe('Yesterday');
    expect(relativeDay(null)).toBe('—');
  });
});

describe('text', () => {
  it('takes the initials of the first two words', () => {
    expect(initials('laura moreno díaz')).toBe('LM');
    expect(initials('')).toBe('');
  });

  it('builds the full name without extra spaces', () => {
    expect(fullName({ first_name: 'Ana', last_name: null })).toBe('Ana');
  });

  it('pluralizes by count', () => {
    expect(pluralize(1, 'appointment')).toBe('1 appointment');
    expect(pluralize(3, 'appointment')).toBe('3 appointments');
  });

  it('formats file sizes', () => {
    expect(formatBytes(500)).toBe('500 B');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
  });

  it('formats money in en-US with no decimals for COP', () => {
    // Intl separates the currency code with a non-breaking space.
    const plain = (text: string) => text.replace(/\s/g, ' ');
    expect(plain(formatMoney(150000))).toBe('COP 150,000');
    expect(plain(formatMoney(null))).toBe('COP 0');
  });
});

describe('catalogs', () => {
  it('returns the label, or the value when it is missing', () => {
    const options = [{ value: 'online', label: 'Online' }];
    expect(labelOf(options, 'online')).toBe('Online');
    expect(labelOf(options, 'other')).toBe('other');
    expect(labelOf(options, null)).toBe('—');
  });

  it('assigns a tone to each severity', () => {
    expect(severityTone('Severe')).toBe('danger');
    expect(severityTone('Moderately severe')).toBe('danger');
    expect(severityTone('Extremely severe')).toBe('danger');
    expect(severityTone('High perceived stress')).toBe('danger');
    expect(severityTone('Moderate')).toBe('warning');
    expect(severityTone('Mild')).toBe('info');
    expect(severityTone('Low self-esteem')).toBe('info');
    expect(severityTone('Minimal')).toBe('success');
    expect(severityTone('Normal')).toBe('success');
    expect(severityTone('Adequate wellbeing')).toBe('success');
    expect(severityTone('Pending')).toBe('neutral');
    expect(severityTone(null)).toBe('neutral');
  });
});
