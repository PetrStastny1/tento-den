import { EventCard } from '../components/EventCard.jsx'
import { LargeTitle } from '../components/LargeTitle.jsx'
import { EmptyState } from '../components/States.jsx'

export function FavoritesScreen({ favorites }) {
  return (
    <div className="min-h-svh pb-[calc(env(safe-area-inset-bottom)+5rem)]">
      <LargeTitle title="Oblíbené" subtitle="Tento den" />
      <main className="mx-auto mt-5 max-w-2xl space-y-4 px-4">
        {!favorites.persistent && (
          <p role="note" className="rounded-2xl bg-amber-100 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
            Prohlížeč nepovoluje ukládání, oblíbené vydrží jen do zavření stránky.
          </p>
        )}
        {favorites.favorites.length === 0 ? (
          <EmptyState title="Zatím nic">Srdíčkem u karty si ji uložíte sem.</EmptyState>
        ) : (
          favorites.favorites.map((card) => (
            <EventCard key={card.id} card={card} saved onToggleFavorite={favorites.toggle} />
          ))
        )}
      </main>
    </div>
  )
}
