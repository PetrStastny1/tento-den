// Days of the year as "MM-DD" strings. Anniversaries ignore the year, so February 29
// is always part of the cycle; "today" follows Europe/Prague for everybody.

const DAYS_IN_MONTH = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
const MONTHS_GENITIVE = [
  'ledna', 'února', 'března', 'dubna', 'května', 'června',
  'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
]

export function isMonthDay(value) {
  const match = /^(\d{2})-(\d{2})$/.exec(value ?? '')
  if (!match) return false
  const month = Number(match[1])
  const day = Number(match[2])
  return month >= 1 && month <= 12 && day >= 1 && day <= DAYS_IN_MONTH[month - 1]
}

export function todayInPrague(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Prague', month: '2-digit', day: '2-digit',
  }).formatToParts(now)
  const get = (type) => parts.find((part) => part.type === type).value
  return `${get('month')}-${get('day')}`
}

export function shiftDay(monthDay, offset) {
  const all = allMonthDays()
  const index = all.indexOf(monthDay)
  return all[(index + offset + all.length) % all.length]
}

export function formatDayTitle(monthDay) {
  const [month, day] = monthDay.split('-').map(Number)
  return `${day}. ${MONTHS_GENITIVE[month - 1]}`
}

// value for <input type="date">: a leap year keeps February 29 selectable
export function toDateInputValue(monthDay) {
  return `2024-${monthDay}`
}

export function fromDateInputValue(value) {
  const monthDay = (value ?? '').slice(5)
  return isMonthDay(monthDay) ? monthDay : null
}

function allMonthDays() {
  return DAYS_IN_MONTH.flatMap((count, month) =>
    Array.from({ length: count }, (_, day) =>
      `${String(month + 1).padStart(2, '0')}-${String(day + 1).padStart(2, '0')}`))
}
