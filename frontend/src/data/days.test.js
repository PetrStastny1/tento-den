import { afterEach, describe, expect, it, vi } from 'vitest'
import { dayResponse, holiday } from '../test/fixtures.js'

const jsonResponse = (data) => ({ ok: true, status: 200, json: async () => data })

async function staticDays() {
  vi.resetModules()
  vi.stubEnv('VITE_DATA_MODE', 'static')
  return import('./days.js')
}

afterEach(() => {
  vi.unstubAllEnvs()
  vi.unstubAllGlobals()
})

describe('statická data', () => {
  it('pohyblivé svátky přidá z movable.json jen pro známý rok a přesné datum', async () => {
    const friday = holiday('Velký pátek', { id: 'velky-patek', date: '04-03' })
    vi.stubGlobal('fetch', vi.fn(async (url) => jsonResponse(url.endsWith('movable.json')
      ? { '2026-04-03': [friday], '2027-03-26': [friday] }
      : dayResponse('04-03'))))
    const { fetchDay, DATA_MODE } = await staticDays()
    expect(DATA_MODE).toBe('static')

    expect((await fetchDay('04-03', { year: 2026 })).holidays.map((h) => h.name)).toEqual(['Velký pátek'])
    expect((await fetchDay('04-03', { year: 2027 })).holidays).toEqual([])
    expect((await fetchDay('04-03')).holidays).toEqual([])
    expect(fetch.mock.calls.map(([url]) => url)).toEqual(['/data/04-03.json', '/data/movable.json', '/data/04-03.json', '/data/movable.json', '/data/04-03.json'])
  })

  it('výpadek movable.json den nerozbije', async () => {
    vi.stubGlobal('fetch', vi.fn(async (url) => (url.endsWith('movable.json') ? { ok: false, status: 404 } : jsonResponse(dayResponse('04-03')))))
    const { fetchDay } = await staticDays()
    const day = await fetchDay('04-03', { year: 2026 })
    expect(day.cards).toHaveLength(10)
    expect(day.holidays).toEqual([])
  })
})
