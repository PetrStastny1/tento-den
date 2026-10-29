import { ArrowUpRight, X } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { ExternalLink } from './ExternalLink.jsx'
import { Flag } from './Flag.jsx'

const TEXTS = {
  cz: { region: 'Jmeniny', meaning: 'Význam a původ', meaningOf: 'Význam jména', variantOf: 'varianta jména', bearer: 'Známý nositel', authors: 'autoři', close: 'Zavřít' },
  sk: { region: 'Meniny', meaning: 'Význam a pôvod', meaningOf: 'Význam mena', variantOf: 'variant mena', bearer: 'Známy nositeľ', authors: 'autori', close: 'Zavrieť' },
}
const COUNTRY_LABELS = { cz: 'Česko', sk: 'Slovensko' }

// One sentence in the day card under the date: „Svátek má Silvie, Silvána a Sylva“ in the chosen
// mode, flags on the right. A name is tappable only when we know its meaning, a bearer or that it is a variant of another name of the day; it opens one small panel
// (a popover on wide screens, a bottom sheet on a phone). No names in either country: no card.
export function NameDays({ nameDays, country, onCountry }) {
  const [open, setOpen] = useState(null) // { name, anchor, trigger } – never more than one panel
  const close = useCallback(() => setOpen(null), [])
  if (nameDays && !nameDays.cz && !nameDays.sk) return null
  const data = nameDays?.[country] ?? null
  const texts = TEXTS[country]
  const entry = data?.names.find((item) => item.name === open?.name) ?? null

  return (
    <section
      aria-label={texts.region}
      lang={country === 'sk' ? 'sk' : 'cs'}
      className="relative mt-4 flex min-h-[52px] items-center gap-2 border-t border-hairline pt-2"
    >
      <p className="relative min-w-0 flex-1 font-rounded text-[15px] leading-snug text-ink-soft">
        {data && (
          <>
            <span className="font-semibold">{data.heading}</span>{' '}
            {data.names.map((item, index) => (
              <span key={item.name}>
                {index > 0 && (index === data.names.length - 1 ? ' a ' : ', ')}
                {item.meaning || item.bearer || item.variantOf ? (
                  <button
                    type="button"
                    aria-haspopup="dialog"
                    aria-expanded={open?.name === item.name}
                    onClick={(event) => {
                      const rect = event.currentTarget.getBoundingClientRect()
                      const anchor = { top: Math.round(rect.bottom + 6), left: Math.round(Math.min(rect.left, window.innerWidth - 376)) }
                      setOpen(open?.name === item.name ? null : { name: item.name, anchor, trigger: event.currentTarget })
                    }}
                    className="-my-3 inline-flex min-h-11 items-center font-semibold text-accent-ink underline decoration-accent/40 decoration-1 underline-offset-4 hover:decoration-accent"
                  >
                    {item.name}
                  </button>
                ) : (
                  <span className="font-semibold text-ink">{item.name}</span>
                )}
              </span>
            ))}
          </>
        )}
      </p>
      <CountrySwitch country={country} onCountry={(value) => { setOpen(null); onCountry(value) }} />
      {entry && <NamePanel key={entry.name} entry={entry} anchor={open.anchor} trigger={open.trigger} source={data.source} texts={texts} onClose={close} />}
    </section>
  )
}

function CountrySwitch({ country, onCountry }) {
  return (
    <div role="radiogroup" aria-label="Jmeniny: Česko / Slovensko" className="relative flex shrink-0 rounded-full bg-paper/70 p-0.5 ring-1 ring-hairline">
      {['cz', 'sk'].map((value) => (
        <button
          key={value}
          type="button"
          role="radio"
          aria-checked={country === value}
          aria-label={COUNTRY_LABELS[value]}
          title={COUNTRY_LABELS[value]}
          onClick={() => onCountry(value)}
          className={`inline-flex size-11 items-center justify-center rounded-full motion-safe:transition ${country === value ? 'bg-card shadow-sm ring-1 ring-hairline' : 'opacity-60 hover:opacity-100'}`}
        >
          <Flag country={value} className="h-3.5 w-[21px]" />
        </button>
      ))}
    </div>
  )
}

// One name: meaning, bearer and one line of attribution. A bottom sheet on a phone, a popover under
// the name on wider screens. Rendered into <body>: the sticky column is its own stacking context and
// scroll container, which would put the cards over the panel or clip it. It closes when the page scrolls. Esc, the close button or a tap outside closes it; focus returns to the name.
function NamePanel({ entry, anchor, trigger, source, texts, onClose }) {
  const panelRef = useRef(null)
  const { meaning, bearer } = entry
  const attribution = meaning?.source ?? source

  useEffect(() => {
    // the name that opened the panel (Safari does not focus a button on click)
    const opener = trigger
    const onKey = (event) => { if (event.key === 'Escape') onClose() }
    const onPointer = (event) => {
      if (panelRef.current && !panelRef.current.contains(event.target) && !opener?.contains(event.target)) onClose()
    }
    panelRef.current?.focus()
    const onScroll = (event) => {
      if (window.matchMedia?.('(min-width: 640px)').matches && !panelRef.current?.contains(event.target)) onClose()
    }
    document.addEventListener('keydown', onKey)
    document.addEventListener('pointerdown', onPointer)
    document.addEventListener('scroll', onScroll, true)
    return () => {
      document.removeEventListener('keydown', onKey)
      document.removeEventListener('pointerdown', onPointer)
      document.removeEventListener('scroll', onScroll, true)
      if (opener?.isConnected) opener.focus()
    }
  }, [onClose, trigger])

  return createPortal(
    <>
      <div aria-hidden="true" className="fixed inset-0 z-40 bg-[oklch(0.15_0.02_60/0.3)] motion-safe:animate-fade sm:hidden" />
      <div
        ref={panelRef}
        role="dialog"
        aria-label={entry.name}
        tabIndex={-1}
        style={{ '--top': `${anchor.top}px`, '--left': `${Math.max(8, anchor.left - 12)}px` }}
        className="fixed inset-x-0 bottom-0 z-50 max-h-[85dvh] overflow-y-auto overscroll-contain rounded-t-[26px] bg-card px-5 pb-[calc(env(safe-area-inset-bottom)+1rem)] pt-3 shadow-[0_-12px_40px_-12px_oklch(0.2_0.02_60/0.35)] ring-1 ring-hairline focus:outline-none motion-safe:animate-sheet sm:inset-x-auto sm:bottom-auto sm:left-[var(--left)] sm:top-[var(--top)] sm:max-h-[calc(100dvh-var(--top)-12px)] sm:w-[360px] sm:rounded-[20px] sm:px-4 sm:pb-2 sm:shadow-[0_12px_40px_-12px_oklch(0.2_0.02_60/0.35)] sm:motion-safe:animate-rise"
      >
        <div aria-hidden="true" className="mx-auto mb-2 h-1.5 w-10 rounded-full bg-hairline sm:hidden" />
        <div className="flex items-start justify-between gap-2">
          <p className="pt-1.5 font-serif text-[22px] font-semibold text-ink">
            {entry.name}
            {entry.variantOf && <span className="font-rounded text-[15px] font-semibold text-ink-soft"> – {texts.variantOf} {entry.variantOf.name}</span>}
          </p>
          <button type="button" aria-label={texts.close} onClick={onClose} className="-mr-2 inline-flex size-11 items-center justify-center rounded-full text-ink-soft hover:bg-accent-wash">
            <X className="size-5" aria-hidden="true" />
          </button>
        </div>
        {meaning && (
          <>
            {/* the meaning of the main name when the article is about it (Sylva → the article Silvie) */}
            <p className="font-rounded text-[12px] font-bold uppercase tracking-[0.14em] text-ink-faint">
              {meaning.aboutName && meaning.aboutName !== entry.name ? `${texts.meaningOf} ${meaning.aboutName}` : texts.meaning}
            </p>
            <p className="mt-1 line-clamp-6 text-pretty font-serif text-[17px] leading-[1.45] text-ink">{meaning.extract}</p>
          </>
        )}
        {bearer && (
          <p className="mt-1 flex flex-wrap items-center gap-x-2 font-rounded text-[15px] text-ink-soft">
            <span>{texts.bearer}:</span>
            <ExternalLink href={bearer.url} plain className="inline-flex min-h-11 items-center gap-1 font-semibold text-accent-ink">
              {bearer.name}
              <ArrowUpRight className="size-4" aria-hidden="true" strokeWidth={2.4} />
            </ExternalLink>
          </p>
        )}
        <p className="flex flex-wrap items-center gap-x-1.5 text-[12px] text-ink-faint">
          <ExternalLink href={attribution.revisionUrl} className="inline-flex min-h-11 items-center font-semibold text-ink-soft">Zdroj</ExternalLink>
          <span aria-hidden="true">·</span>
          <ExternalLink href={attribution.historyUrl} className="inline-flex min-h-11 items-center">{texts.authors}</ExternalLink>
          <span aria-hidden="true">·</span>
          <ExternalLink href={attribution.licenseUrl} license className="inline-flex min-h-11 items-center">{attribution.license}</ExternalLink>
        </p>
      </div>
    </>,
    document.body,
  )
}
