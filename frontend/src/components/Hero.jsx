import { forwardRef } from 'react'

// The big editorial title: "28." in the day colour, the month below. One h1 whose
// accessible name stays the plain date ("28. října").
export const Hero = forwardRef(function Hero({ number, word, kicker, tagline, children }, ref) {
  return (
    <header className="pt-[calc(env(safe-area-inset-top)+1.75rem)] lg:sticky lg:top-0 lg:pt-16">
      <p className="flex items-center gap-2 font-rounded text-[13px] font-bold uppercase tracking-[0.18em] text-ink-faint">
        <span aria-hidden="true" className="inline-block size-2 rounded-full bg-accent shadow-[0_0_12px_var(--accent)]" />
        {kicker}
      </p>
      <h1 ref={ref} className="mt-3 font-serif font-semibold tracking-tight">
        {number && (
          <>
            <span className="block text-[96px] leading-[0.85] text-accent-ink lg:text-[132px]">{number}</span>{' '}
          </>
        )}
        <span className="block text-[44px] leading-tight text-ink lg:text-[56px]">{word}</span>
      </h1>
      {tagline && <p className="mt-3 max-w-sm text-[17px] leading-snug text-ink-soft">{tagline}</p>}
      {children}
    </header>
  )
})
