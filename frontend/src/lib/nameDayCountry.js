import { useCallback, useState } from 'react'

const KEY = 'tento-den:name-days'
const COUNTRIES = ['cz', 'sk']

// The name day mode (CZ by default) is remembered in the browser; without storage it is CZ.
function readStored() {
  try {
    const value = window.localStorage.getItem(KEY)
    return COUNTRIES.includes(value) ? value : 'cz'
  } catch {
    return 'cz'
  }
}

export function useNameDayCountry() {
  const [country, setCountry] = useState(readStored)
  const choose = useCallback((value) => {
    if (!COUNTRIES.includes(value)) return
    setCountry(value)
    try {
      window.localStorage.setItem(KEY, value)
    } catch {
      // storage blocked: the choice lasts until the page is closed
    }
  }, [])
  return [country, choose]
}
