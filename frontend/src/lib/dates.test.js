import { describe, expect, it } from 'vitest'
import { formatDayTitle, fromDateInputValue, isMonthDay, shiftDay, todayInPrague } from './dates.js'

describe('dates', () => {
  it('validuje MM-DD včetně 29. února', () => {
    expect(isMonthDay('02-29')).toBe(true)
    expect(isMonthDay('02-30')).toBe(false)
    expect(isMonthDay('13-01')).toBe(false)
    expect(isMonthDay('1028')).toBe(false)
  })

  it('listuje přes 29. února a přes konec roku', () => {
    expect(shiftDay('02-28', 1)).toBe('02-29')
    expect(shiftDay('03-01', -1)).toBe('02-29')
    expect(shiftDay('12-31', 1)).toBe('01-01')
    expect(shiftDay('01-01', -1)).toBe('12-31')
  })

  it('„dnes“ je podle Europe/Prague', () => {
    expect(todayInPrague(new Date('2026-10-27T23:30:00Z'))).toBe('10-28')
    expect(todayInPrague(new Date('2026-07-01T21:59:00Z'))).toBe('07-01')
    expect(todayInPrague(new Date('2026-07-01T22:00:00Z'))).toBe('07-02')
  })

  it('formátuje titulek a čte hodnotu výběru data', () => {
    expect(formatDayTitle('10-28')).toBe('28. října')
    expect(formatDayTitle('02-29')).toBe('29. února')
    expect(fromDateInputValue('2024-02-29')).toBe('02-29')
    expect(fromDateInputValue('')).toBeNull()
  })
})
