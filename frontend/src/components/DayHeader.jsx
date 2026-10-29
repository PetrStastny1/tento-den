import { forwardRef } from 'react'

// The head of a day as one card in the style of the anniversary cards: kicker, the date on one line
// ("29. října", one h1 whose accessible name is the plain date) and the name days below it.
export const DayHeader = forwardRef(function DayHeader({ kicker, number, word, tagline, nameDays }, ref) {
  return (
    <header className="relative rounded-[28px] bg-card p-5 ring-1 ring-hairline shadow-[0_1px_2px_oklch(0.2_0.02_60/0.06),0_12px_32px_-16px_oklch(0.2_0.02_60/0.25)] sm:p-6">
      {/* no overflow-hidden: the name panel opens below the card */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-28 rounded-t-[28px] bg-gradient-to-b from-accent-wash to-transparent" />
      <p className="relative flex items-center gap-2 font-rounded text-[12px] font-bold uppercase tracking-[0.18em] text-ink-faint">
        <span aria-hidden="true" className="inline-block size-2 rounded-full bg-accent shadow-[0_0_12px_var(--accent)]" />
        {kicker}
      </p>
      <h1 ref={ref} className="relative mt-2 font-serif text-[44px] font-semibold leading-none tracking-tight lg:text-[52px]">
        <span className="text-accent-ink">{number}</span> <span className="text-ink">{word}</span>
      </h1>
      {nameDays}
      {tagline && <p className="relative mt-3 text-[15px] leading-snug text-ink-soft">{tagline}</p>}
    </header>
  )
})
