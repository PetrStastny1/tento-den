// The single data module. Two modes, chosen at build time (VITE_DATA_MODE):
// - "api" (default): the live backend, GET /api/day?date=MM-DD[&year=YYYY]
// - "static": pre-exported files public/data/MM-DD.json (demo without a backend); movable holidays
//   (Easter) are in public/data/movable.json by exact date and are added when the year is known
import { isMonthDay } from '../lib/dates.js'

export const DATA_MODE = import.meta.env.VITE_DATA_MODE === 'static' ? 'static' : 'api'

export class DataError extends Error {}

// year: only when the exact date is known ("today") – it decides the movable holidays
export async function fetchDay(monthDay, { signal, year } = {}) {
  if (!isMonthDay(monthDay)) throw new DataError('Neplatné datum.')
  const url = DATA_MODE === 'static'
    ? `${import.meta.env.BASE_URL}data/${monthDay}.json`
    : `/api/day?date=${monthDay}${year ? `&year=${year}` : ''}`

  const data = await getJson(url, signal)
  if (!data || data.date !== monthDay || !Array.isArray(data.cards)) {
    throw new DataError('Server vrátil neočekávaná data.')
  }
  const holidays = Array.isArray(data.holidays) ? data.holidays : []
  if (DATA_MODE === 'static' && year) {
    holidays.push(...await movableHolidays(`${year}-${monthDay}`, signal))
  }
  return { ...data, holidays, nameDays: data.nameDays ?? { cz: null, sk: null } }
}

async function movableHolidays(date, signal) {
  try {
    const movable = await getJson(`${import.meta.env.BASE_URL}data/movable.json`, signal)
    return Array.isArray(movable?.[date]) ? movable[date] : []
  } catch (error) {
    if (error?.name === 'AbortError') throw error
    return [] // movable holidays are an addition; the day works without them
  }
}

async function getJson(url, signal) {
  let response
  try {
    response = await fetch(url, { signal, headers: { Accept: 'application/json' } })
  } catch (error) {
    if (error?.name === 'AbortError') throw error
    throw new DataError('Nepodařilo se spojit se serverem. Zkontrolujte připojení.')
  }
  if (!response.ok) throw new DataError(`Data se nepodařilo načíst (HTTP ${response.status}).`)
  return response.json().catch(() => null)
}
