import { useCallback, useState } from 'react'

const KEY = 'tento-den:favorites'
const MAX = 200

// localStorage may be missing or throw (private mode, blocked storage): every access is guarded
// and the app keeps working with favorites held in memory only.
function readStored() {
  try {
    const raw = window.localStorage.getItem(KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return { items: Array.isArray(parsed) ? parsed.filter(isFavorite) : [], persistent: true }
  } catch {
    return { items: [], persistent: false }
  }
}

function writeStored(items) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(items))
    return true
  } catch {
    return false
  }
}

function isFavorite(item) {
  return item && typeof item.id === 'number' && typeof item.text === 'string' && typeof item.date === 'string'
}

export function useFavorites() {
  const [state, setState] = useState(readStored)

  const toggle = useCallback((card) => {
    setState((current) => {
      const exists = current.items.some((item) => item.id === card.id)
      const items = exists
        ? current.items.filter((item) => item.id !== card.id)
        : [snapshot(card), ...current.items].slice(0, MAX)
      return { items, persistent: writeStored(items) && current.persistent }
    })
  }, [])

  const isSaved = useCallback((id) => state.items.some((item) => item.id === id), [state.items])

  return { favorites: state.items, persistent: state.persistent, toggle, isSaved }
}

// a copy of the card, so the list works offline and in the static demo
function snapshot(card) {
  const { id, date, year, yearLabel, section, text, sensitive, stateDay, source, article } = card
  return { id, date, year, yearLabel, section, text, sensitive, stateDay, source, article } // the anniversary belongs to one year only
}
