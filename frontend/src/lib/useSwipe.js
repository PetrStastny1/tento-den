import { useRef } from 'react'

// Horizontal swipe: left = next, right = previous. Vertical scrolling is left alone.
export function useSwipe({ onLeft, onRight, threshold = 60 }) {
  const start = useRef(null)
  return {
    onTouchStart(event) {
      const touch = event.touches[0]
      start.current = { x: touch.clientX, y: touch.clientY }
    },
    onTouchEnd(event) {
      if (!start.current) return
      const touch = event.changedTouches[0]
      const dx = touch.clientX - start.current.x
      const dy = touch.clientY - start.current.y
      start.current = null
      if (Math.abs(dx) < threshold || Math.abs(dy) > Math.abs(dx) / 2) return
      if (dx < 0) onLeft()
      else onRight()
    },
  }
}
