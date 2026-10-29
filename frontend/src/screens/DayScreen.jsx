import { CalendarX2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Navigate, useLocation, useNavigate, useParams } from 'react-router'
import { DayNav } from '../components/DayNav.jsx'
import { EventCard } from '../components/EventCard.jsx'
import { Hero } from '../components/Hero.jsx'
import { EmptyState, ErrorState, LoadingState } from '../components/States.jsx'
import { TopBar } from '../components/TopBar.jsx'
import { fetchDay } from '../data/days.js'
import { formatDayTitle, isMonthDay, shiftDay, todayInPrague } from '../lib/dates.js'
import { applyDayHue } from '../lib/theme.js'
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
  const titleRef = useRef(null)
  const [state, setState] = useState({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)
  const swipe = useSwipe({
    onLeft: () => navigate(`/den/${shiftDay(date, 1)}`),
    onRight: () => navigate(`/den/${shiftDay(date, -1)}`),
  })
  const title = formatDayTitle(date)
  const [number, word] = title.split(' ')
  const isToday = date === todayInPrague()

  useEffect(() => applyDayHue(date), [date])

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
    <div {...swipe} className="min-h-svh">
      <TopBar title={title} watchRef={titleRef} />
      <div className="mx-auto max-w-6xl px-5 pb-[calc(env(safe-area-inset-bottom)+7rem)] lg:grid lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:gap-16 lg:px-10">
        <div>
          <Hero
            ref={titleRef}
            number={number}
            word={word}
            kicker={isToday ? 'Dnes · Tento den' : 'Tento den'}
            tagline="Pět výročí, která stojí za to znát – z Česka i ze světa."
          >
            <DayNav date={date} />
          </Hero>
        </div>
        <main className="mt-8 space-y-5 lg:mt-16">
          {state.status === 'loading' && <LoadingState />}
          {state.status === 'error' && <ErrorState message={state.message} onRetry={retry} />}
          {state.status === 'ready' && state.cards.length === 0 && (
            <EmptyState icon={CalendarX2} title="Pro tento den nic nemáme">Zkuste předchozí nebo další den.</EmptyState>
          )}
          {state.status === 'ready' && state.cards.map((card, index) => (
            <EventCard key={card.id} card={card} index={index} saved={favorites.isSaved(card.id)} onToggleFavorite={favorites.toggle} />
          ))}
        </main>
      </div>
    </div>
  )
}
