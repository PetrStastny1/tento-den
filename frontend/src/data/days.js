// The single data module. Two modes, chosen at build time (VITE_DATA_MODE):
// - "api" (default): the live backend, GET /api/day?date=MM-DD
// - "static": pre-exported files public/data/MM-DD.json (demo without a backend)
import { isMonthDay } from '../lib/dates.js'

export const DATA_MODE = import.meta.env.VITE_DATA_MODE === 'static' ? 'static' : 'api'

export class DataError extends Error {}

export async function fetchDay(monthDay, { signal } = {}) {
  if (!isMonthDay(monthDay)) throw new DataError('Neplatné datum.')
  const url = DATA_MODE === 'static'
    ? `${import.meta.env.BASE_URL}data/${monthDay}.json`
    : `/api/day?date=${monthDay}`

  let response
  try {
    response = await fetch(url, { signal, headers: { Accept: 'application/json' } })
  } catch (error) {
    if (error?.name === 'AbortError') throw error
    throw new DataError('Nepodařilo se spojit se serverem. Zkontrolujte připojení.')
  }
  if (!response.ok) throw new DataError(`Data se nepodařilo načíst (HTTP ${response.status}).`)

  const data = await response.json().catch(() => null)
  if (!data || data.date !== monthDay || !Array.isArray(data.cards)) {
    throw new DataError('Server vrátil neočekávaná data.')
  }
  return data
}
