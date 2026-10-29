import { ArrowUpRight, ChevronDown, Scroll } from 'lucide-react'
import { useState } from 'react'
import { ExternalLink } from './ExternalLink.jsx'

// A state holiday or significant day without an event of the day page: the official name and type
// from the act and the introduction of the Wikipedia article, with the same attribution as an event.
export function HolidayCard({ holiday }) {
  const { article } = holiday
  // the introduction stays whole (it is the source text); a long one is only folded to five lines
  const [expanded, setExpanded] = useState(false)
  const long = (article?.extract.length ?? 0) > 280
  return (
    <article
      id={`svatek-${holiday.id}`}
      aria-label={`${holiday.typeLabel}: ${holiday.name}`}
      className="relative overflow-hidden rounded-[28px] bg-card p-5 ring-2 ring-accent/40 shadow-[0_1px_2px_oklch(0.2_0.02_60/0.06),0_12px_32px_-16px_oklch(0.2_0.02_60/0.25)] motion-safe:animate-rise sm:p-6"
    >
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-accent-wash to-transparent" />
      <p className="relative">
        <span className="rounded-full bg-accent-ink px-3 py-1 font-rounded text-[13px] font-bold text-paper">{holiday.typeLabel}</span>
      </p>
      <h2 className="relative mt-3 text-balance font-serif text-[30px] font-semibold leading-tight text-ink">{holiday.name}</h2>
      <p className="relative mt-1 font-rounded text-[13px] font-semibold text-ink-faint">podle zákona č. 245/2000 Sb.</p>

      {article && (
        <>
          <p id={`uvod-${holiday.id}`} className={`relative mt-4 text-pretty font-serif text-[19px] leading-[1.5] text-ink ${long && !expanded ? 'line-clamp-5' : ''}`}>
            {article.extract}
          </p>
          {long && (
            <button
              type="button"
              aria-expanded={expanded}
              aria-controls={`uvod-${holiday.id}`}
              onClick={() => setExpanded((value) => !value)}
              className="relative mt-1 flex min-h-11 items-center gap-1 font-rounded text-[15px] font-semibold text-accent-ink"
            >
              {expanded ? 'Méně' : 'Celý úvod'}
              <ChevronDown className={`size-4 motion-safe:transition-transform ${expanded ? 'rotate-180' : ''}`} aria-hidden="true" strokeWidth={2.4} />
            </button>
          )}
          <ExternalLink
            href={article.url}
            plain
            className="relative mt-3 inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-full bg-paper/80 px-4 font-rounded text-[15px] font-semibold text-accent-ink ring-1 ring-hairline hover:bg-accent-wash motion-safe:transition-colors"
          >
            <span className="truncate">{article.title}</span>
            <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" strokeWidth={2.4} />
          </ExternalLink>
          <footer className="relative mt-5 flex flex-wrap items-center justify-between gap-x-4 border-t border-hairline pt-1 text-[12px] text-ink-faint">
            <span className="inline-flex min-h-11 items-center gap-1.5">
              <Scroll className="size-3.5" aria-hidden="true" />
              Wikipedie, „{article.source.title}“
            </span>
            <span className="inline-flex items-center gap-3">
              <ExternalLink href={article.source.revisionUrl} className="inline-flex min-h-11 items-center font-semibold text-ink-soft">
                Zdroj
              </ExternalLink>
              <ExternalLink href={article.source.historyUrl} className="inline-flex min-h-11 items-center">autoři</ExternalLink>
              <ExternalLink href={article.source.licenseUrl} license className="inline-flex min-h-11 items-center">{article.source.license}</ExternalLink>
            </span>
          </footer>
        </>
      )}
    </article>
  )
}
