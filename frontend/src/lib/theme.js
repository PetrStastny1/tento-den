// Visual personality of a day: a hue that follows the seasons (winter blue → spring green →
// summer gold → autumn orange → winter violet) and a human "how long ago" for the year.

const DAYS_BEFORE_MONTH = [0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335]
// day of the (leap) year → hue; unwrapped so the interpolation never jumps around the wheel
const STOPS = [[0, 255], [80, 150], [172, 90], [265, 62], [320, 42], [355, -50], [366, -105]]

export function dayHue(monthDay) {
  const [month, day] = monthDay.split('-').map(Number)
  const index = DAYS_BEFORE_MONTH[month - 1] + day - 1
  for (let i = 1; i < STOPS.length; i++) {
    const [toDay, toHue] = STOPS[i]
    const [fromDay, fromHue] = STOPS[i - 1]
    if (index <= toDay) {
      const hue = fromHue + ((toHue - fromHue) * (index - fromDay)) / (toDay - fromDay)
      return Math.round(((hue % 360) + 360) % 360)
    }
  }
  return STOPS[0][1]
}

export function applyDayHue(monthDay) {
  document.documentElement.style.setProperty('--day-hue', String(dayHue(monthDay)))
}

// there is no year 0: from 45 BC to AD 2026 it is 2070 years
export function yearsAgo(year, currentYear = new Date().getFullYear()) {
  const years = year < 0 ? currentYear - year - 1 : currentYear - year
  if (years <= 0) return 'letos'
  if (years === 1) return 'před rokem'
  return `před ${years.toLocaleString('cs-CZ')} lety`
}
