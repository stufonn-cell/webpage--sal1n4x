import type { CSSProperties } from 'react';

/** Typed CSS variables for staggering entrances and transitions. */
export function cssVars(vars: Record<`--${string}`, string | number>): CSSProperties {
  return vars as CSSProperties;
}

export function enterDelay(ms: number): CSSProperties {
  return cssVars({ '--enter-delay': `${ms}ms` });
}

export function revealDelay(ms: number): CSSProperties {
  return cssVars({ '--reveal-delay': `${ms}ms` });
}
