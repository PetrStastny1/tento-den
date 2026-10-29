import { Heart } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { EventCard } from '../components/EventCard.jsx'
import { Hero } from '../components/Hero.jsx'
import { EmptyState } from '../components/States.jsx'
import { TopBar } from '../components/TopBar.jsx'
import { todayInPrague } from '../lib/dates.js'
import { applyDayHue } from '../lib/theme.js'

export function FavoritesScreen({ favorites }) {
  const titleRef = useRef(null)
  const count = favorites.favorites.length

  useEffect(() => applyDayHue(todayInPrague()), [])

  return (
    <div className="min-h-svh">
      <TopBar title="Oblíbené" watchRef={titleRef} />
      <div className="mx-auto max-w-6xl px-5 pb-[calc(env(safe-area-inset-bottom)+7rem)] lg:grid lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:gap-16 lg:px-10">
        <div>
          <Hero
            ref={titleRef}
            word="Oblíbené"
            kicker="Tento den"
            tagline={count === 0 ? 'Výročí, ke kterým se chcete vracet.' : `Uložená výročí: ${count}.`}
          />
        </div>
        <main className="mt-8 space-y-5 lg:mt-16">
          {!favorites.persistent && (
            <p role="note" className="glass rounded-2xl px-4 py-3 text-[15px] text-ink-soft">
              Prohlížeč nepovoluje ukládání, oblíbené vydrží jen do zavření stránky.
            </p>
          )}
          {count === 0 ? (
            <EmptyState icon={Heart} title="Zatím nic">Srdíčkem u karty si ji uložíte sem.</EmptyState>
          ) : (
            favorites.favorites.map((card, index) => (
              <EventCard key={card.id} card={card} index={index} saved showDate onToggleFavorite={favorites.toggle} />
            ))
          )}
        </main>
      </div>
    </div>
  )
}
