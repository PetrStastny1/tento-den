import { useEffect, useRef, useState } from 'react'

// iOS-style large title: big in the content, a small title appears in the translucent bar
// once the big one scrolls away.
export function LargeTitle({ title, subtitle, children }) {
  const titleRef = useRef(null)
  const [collapsed, setCollapsed] = useState(false)

  useEffect(() => {
    const element = titleRef.current
    if (!element || typeof IntersectionObserver === 'undefined') return undefined
    const observer = new IntersectionObserver(([entry]) => setCollapsed(!entry.isIntersecting), { rootMargin: '-56px 0px 0px 0px' })
    observer.observe(element)
    return () => observer.disconnect()
  }, [])

  return (
    <>
      <div className={`fixed inset-x-0 top-0 z-20 pt-[env(safe-area-inset-top)] bar-surface motion-safe:transition-[border-color] ${
        collapsed ? 'border-b border-black/10 dark:border-white/10' : 'border-b border-transparent'
      }`}>
        <div className="mx-auto flex h-12 max-w-2xl items-center justify-center px-4">
          <p aria-hidden={!collapsed} className={`text-[17px] font-semibold motion-safe:transition-opacity ${collapsed ? 'opacity-100' : 'opacity-0'}`}>
            {title}
          </p>
        </div>
      </div>
      <header className="mx-auto max-w-2xl px-4 pt-[calc(env(safe-area-inset-top)+3.5rem)]">
        <p className="text-sm font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{subtitle}</p>
        <h1 ref={titleRef} className="text-[34px] font-bold leading-tight tracking-tight">{title}</h1>
        {children}
      </header>
    </>
  )
}
