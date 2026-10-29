import { describe, expect, it } from 'vitest'
import { dayHue, yearsAgo } from './theme.js'

describe('theme', () => {
  it('odstín dne sleduje roční období a je spojitý', () => {
    expect(dayHue('01-01')).toBe(255) // zimní modrá
    expect(dayHue('07-01')).toBeGreaterThanOrEqual(80) // léto: zlatá
    expect(dayHue('07-01')).toBeLessThanOrEqual(95)
    expect(dayHue('10-28')).toBeGreaterThanOrEqual(45) // podzim: oranžová, ne červená (OKLCH)
    expect(dayHue('10-28')).toBeLessThanOrEqual(60)
    for (const day of ['02-28', '02-29', '03-01', '12-31']) {
      expect(dayHue(day)).toBeGreaterThanOrEqual(0)
      expect(dayHue(day)).toBeLessThan(360)
    }
    expect(Math.abs(dayHue('12-31') - dayHue('01-01'))).toBeLessThan(10) // rok se uzavře plynule
  })

  it('„před X lety“ česky, bez roku 0', () => {
    expect(yearsAgo(1918, 2026)).toBe('před 108 lety')
    expect(yearsAgo(2025, 2026)).toBe('před rokem')
    expect(yearsAgo(2026, 2026)).toBe('letos')
    expect(yearsAgo(-45, 2026)).toBe('před 2 070 lety')
  })
})
