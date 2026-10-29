import { useEffect, useState } from 'react'
import { Navigate, useLocation, useNavigate, useParams } from 'react-router'
import { DayNav } from '../components/DayNav.jsx'
import { EventCard } from '../components/EventCard.jsx'
import { LargeTitle } from '../components/LargeTitle.jsx'
import { EmptyState, ErrorState, LoadingState } from '../components/States.jsx'
import { fetchDay } from '../data/days.js'
import { formatDayTitle, isMonthDay, shiftDay, todayInPrague } from '../lib/dates.js'
import { useSwipe } from '../lib/useSwipe.js'

export function DayScreen({ favorites }) {
  const params = useParams()
  const date = params.date ?? todayInPrague()
  if (!isMonthDay(date)) return <Navigate to="/" replace />
  return <Day key={date} date={date} favorites={favorites} />
}

function Day({ date, favorites }) {
  const navigate = useNavigate()
  const location = useLocation()
  const [state, setState] = useState({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const swipe = useSwipe({
    onLeft: () => navigate(`/den/${shiftDay(date, 1)}`),
    onRight: () => navigate(`/den/${shiftDay(date, -1)}`),
  })

  useEffect(() => {
    const controller = new AbortController()
    fetchDay(date, { signal: controller.signal })
      .then((data) => setState({ status: 'ready', cards: data.cards }))
      .catch((error) => {
        if (error?.name !== 'AbortError') setState({ status: 'error', message: error.message })
      })
    return () => controller.abort()
  }, [date, attempt])

  useEffect(() => {
    if (state.status === 'ready' && location.hash) {
      document.getElementById(location.hash.slice(1))?.scrollIntoView()
    }
  }, [state.status, location.hash])

  const retry = () => {
    setState({ status: 'loading' })
    setAttempt((value) => value + 1)
  }

  return (
    <div {...swipe} className="min-h-svh pb-[calc(env(safe-area-inset-bottom)+5rem)]">
      <LargeTitle title={formatDayTitle(date)} subtitle="Tento den">
        <DayNav date={date} />
      </LargeTitle>
      <main className="mx-auto mt-5 max-w-2xl space-y-4 px-4">
        {state.status === 'loading' && <LoadingState />}
        {state.status === 'error' && <ErrorState message={state.message} onRetry={retry} />}
        {state.status === 'ready' && state.cards.length === 0 && (
          <EmptyState title="Pro tento den nic nemáme">Zkuste předchozí nebo další den.</EmptyState>
        )}
        {state.status === 'ready' && state.cards.map((card) => (
          <EventCard key={card.id} card={card} saved={favorites.isSaved(card.id)} onToggleFavorite={favorites.toggle} />
        ))}
      </main>
    </div>
  )
}
