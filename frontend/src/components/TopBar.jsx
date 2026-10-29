import { useEffect, useState } from 'react'

// A floating glass capsule with the title that appears once the hero has scrolled away
// (iOS large-title behaviour). Desktop keeps the hero sticky, so it is not needed there.
export function TopBar({ title, watchRef }) {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const element = watchRef.current
    if (!element || typeof IntersectionObserver === 'undefined') return undefined
    const observer = new IntersectionObserver(([entry]) => setVisible(!entry.isIntersecting))
    observer.observe(element)
    return () => observer.disconnect()
  }, [watchRef])

  return (
    <div
      aria-hidden={!visible}
      className={`pointer-events-none fixed inset-x-0 top-[calc(env(safe-area-inset-top)+10px)] z-30 flex justify-center lg:hidden ${
        visible ? 'opacity-100' : '-translate-y-3 opacity-0'
      } motion-safe:transition-all motion-safe:duration-300`}
    >
      <p className="glass flex h-11 items-center rounded-full px-5 font-serif text-[17px] font-semibold">{title}</p>
    </div>
  )
}
