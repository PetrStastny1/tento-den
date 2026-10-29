import { ArrowUpRight, Globe, Heart, MapPin, Scroll, Share } from 'lucide-react'
import { useState } from 'react'
import { formatDayTitle } from '../lib/dates.js'
import { cardUrl, shareCard } from '../lib/share.js'
import { yearsAgo } from '../lib/theme.js'
import { ExternalLink } from './ExternalLink.jsx'

const SHARE_MESSAGES = { copied: 'Odkaz zkopírován', failed: 'Sdílení se nepodařilo' }

export function EventCard({ card, saved, onToggleFavorite, index = 0, showDate = false }) {
  const [shareMessage, setShareMessage] = useState(null)
  const { sensitive } = card
  const SectionIcon = card.section === 'cz' ? MapPin : Globe
  const sectionLabel = card.section === 'cz' ? 'Česko' : 'Svět'

  async function handleShare() {
    const result = await shareCard(card, cardUrl(card))
    setShareMessage(SHARE_MESSAGES[result] ?? null)
  }

  return (
    <article
      id={`karta-${card.id}`}
      aria-label={`${card.yearLabel}, ${sectionLabel}`}
      style={{ animationDelay: `${Math.min(index, 6) * 70}ms` }}
      className="relative scroll-mt-24 overflow-hidden rounded-[28px] bg-card p-5 ring-1 ring-hairline shadow-[0_1px_2px_oklch(0.2_0.02_60/0.06),0_12px_32px_-16px_oklch(0.2_0.02_60/0.25)] motion-safe:animate-rise sm:p-6"
    >
      {!sensitive && (
        <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-accent-wash to-transparent" />
      )}

      <header className="relative flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className={`font-serif text-[56px] font-semibold leading-[0.95] tracking-tight tabular-nums ${sensitive ? 'text-ink-soft' : 'text-accent-ink'}`}>
            {card.yearLabel}
          </p>
          <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 font-rounded text-[13px] font-semibold text-ink-faint">
            <span className="inline-flex items-center gap-1 rounded-full bg-paper/80 px-2.5 py-1 text-ink-soft ring-1 ring-hairline">
              <SectionIcon className="size-3.5" aria-hidden="true" strokeWidth={2.4} />
              {sectionLabel}
            </span>
            <span>{showDate ? `${formatDayTitle(card.date)} · ` : ''}{yearsAgo(card.year)}</span>
          </p>
        </div>
        <div className="-mr-2 -mt-1 flex shrink-0">
          <IconButton label={saved ? 'Odebrat z oblíbených' : 'Přidat do oblíbených'} pressed={saved} onClick={() => onToggleFavorite(card)}>
            <Heart
              key={saved ? 'saved' : 'not-saved'}
              className={`size-6 ${saved ? 'fill-rose-500 text-rose-500 motion-safe:animate-pop' : ''}`}
              aria-hidden="true"
              strokeWidth={2}
            />
          </IconButton>
          <IconButton label="Sdílet" onClick={handleShare}>
            <Share className="size-6" aria-hidden="true" strokeWidth={2} />
          </IconButton>
        </div>
      </header>

      <p className="relative mt-4 text-pretty font-serif text-[19px] leading-[1.5] text-ink">{card.text}</p>

      {card.article && (
        <ExternalLink
          href={card.article.url}
          plain
          className="relative mt-4 inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-full bg-paper/80 px-4 font-rounded text-[15px] font-semibold text-accent-ink ring-1 ring-hairline hover:bg-accent-wash motion-safe:transition-colors"
        >
          <span className="truncate">{card.article.title}</span>
          <ArrowUpRight className="size-4 shrink-0" aria-hidden="true" strokeWidth={2.4} />
        </ExternalLink>
      )}

      <footer className="relative mt-5 flex flex-wrap items-center justify-between gap-x-4 border-t border-hairline pt-1 text-[12px] text-ink-faint">
        <span className="inline-flex min-h-11 items-center gap-1.5">
          <Scroll className="size-3.5" aria-hidden="true" />
          Wikipedie, „{card.source.title}“
        </span>
        <span className="inline-flex items-center gap-3">
          <ExternalLink href={card.source.revisionUrl} className="inline-flex min-h-11 items-center font-semibold text-ink-soft">
            Zdroj
          </ExternalLink>
          <ExternalLink href={card.source.historyUrl} className="inline-flex min-h-11 items-center">autoři</ExternalLink>
          <ExternalLink href={card.source.licenseUrl} license className="inline-flex min-h-11 items-center">{card.source.license}</ExternalLink>
        </span>
      </footer>

      <p role="status" className="relative text-sm font-medium text-ink-soft empty:hidden">{shareMessage}</p>
    </article>
  )
}

function IconButton({ label, pressed, onClick, children }) {
  return (
    <button
      type="button"
      aria-label={label}
      aria-pressed={pressed}
      onClick={onClick}
      className="inline-flex size-11 items-center justify-center rounded-full text-ink-soft hover:bg-accent-wash hover:text-ink motion-safe:transition active:scale-90"
    >
      {children}
    </button>
  )
}
