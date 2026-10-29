import { Heart, Share } from 'lucide-react'
import { useState } from 'react'
import { cardUrl, shareCard } from '../lib/share.js'
import { ExternalLink } from './ExternalLink.jsx'

const SHARE_MESSAGES = { copied: 'Odkaz zkopírován', failed: 'Sdílení se nepodařilo' }

export function EventCard({ card, saved, onToggleFavorite }) {
  const [shareMessage, setShareMessage] = useState(null)
  const { sensitive } = card

  async function handleShare() {
    const result = await shareCard(card, cardUrl(card))
    setShareMessage(SHARE_MESSAGES[result] ?? null)
  }

  return (
    <article
      id={`karta-${card.id}`}
      aria-label={`${card.yearLabel}, ${card.section === 'cz' ? 'Česko' : 'Svět'}`}
      className={`scroll-mt-28 rounded-3xl p-5 shadow-sm ring-1 ${
        sensitive
          ? 'bg-stone-100 ring-stone-200 dark:bg-stone-900 dark:ring-stone-800'
          : 'bg-white ring-black/5 dark:bg-stone-900 dark:ring-white/10'
      }`}
    >
      <header className="flex items-start justify-between gap-3">
        <div>
          <p className={`text-4xl font-bold tabular-nums tracking-tight ${
            sensitive ? 'text-stone-600 dark:text-stone-300' : 'text-orange-600 dark:text-orange-400'
          }`}>
            {card.yearLabel}
          </p>
          <span className="mt-1 inline-block rounded-full bg-stone-200/70 px-2.5 py-0.5 text-xs font-medium text-stone-700 dark:bg-stone-800 dark:text-stone-300">
            {card.section === 'cz' ? 'Česko' : 'Svět'}
          </span>
        </div>
        <div className="-mr-2 -mt-2 flex">
          <IconButton label={saved ? 'Odebrat z oblíbených' : 'Přidat do oblíbených'} pressed={saved} onClick={() => onToggleFavorite(card)}>
            <Heart className={`size-6 ${saved ? 'fill-rose-500 text-rose-500' : ''}`} aria-hidden="true" />
          </IconButton>
          <IconButton label="Sdílet" onClick={handleShare}>
            <Share className="size-6" aria-hidden="true" />
          </IconButton>
        </div>
      </header>

      <p className="mt-3 text-[17px] leading-relaxed text-stone-900 dark:text-stone-100">{card.text}</p>

      {card.article && (
        <p className="mt-3 text-[15px] text-stone-600 dark:text-stone-400">
          Více: <ExternalLink href={card.article.url}>{card.article.title}</ExternalLink>
        </p>
      )}

      <footer className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500 dark:text-stone-400">
        <ExternalLink href={card.source.revisionUrl} className="inline-flex min-h-11 items-center font-medium text-stone-700 dark:text-stone-300">
          Zdroj
        </ExternalLink>
        <span>
          Wikipedie, „{card.source.title}“ (<ExternalLink href={card.source.historyUrl}>autoři</ExternalLink>),{' '}
          <ExternalLink href={card.source.licenseUrl} license>{card.source.license}</ExternalLink>
        </span>
      </footer>

      <p role="status" className="text-sm text-stone-600 dark:text-stone-400">{shareMessage}</p>
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
      className="inline-flex size-11 items-center justify-center rounded-full text-stone-600 hover:bg-stone-100 active:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800 motion-safe:transition-colors"
    >
      {children}
    </button>
  )
}
