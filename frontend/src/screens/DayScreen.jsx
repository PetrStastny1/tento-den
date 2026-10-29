import { CalendarX2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Navigate, useLocation, useNavigate, useParams } from 'react-router'
import { DayNav } from '../components/DayNav.jsx'
import { EventCard } from '../components/EventCard.jsx'
import { HolidayCard } from '../components/HolidayCard.jsx'
import { NameDays } from '../components/NameDays.jsx'
import { DayHeader } from '../components/DayHeader.jsx'
import { EmptyState, ErrorState, LoadingState } from '../components/States.jsx'
import { TopBar } from '../components/TopBar.jsx'
import { fetchDay } from '../data/days.js'
import { formatDayTitle, isMonthDay, shiftDay, todayInPrague, yearInPrague } from '../lib/dates.js'
import { applyDayHue } from '../lib/theme.js'
import { useSwipe } from '../lib/useSwipe.js'

export function DayScreen({ favorites, nameDayCountry }) {
  const params = useParams()
  const date = params.date ?? todayInPrague()
  if (!isMonthDay(date)) return <Navigate to="/" replace />
  return <Day key={date} date={date} favorites={favorites} nameDayCountry={nameDayCountry} />
}

function Day({ date, favorites, nameDayCountry }) {
  const [country, setCountry] = nameDayCountry
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
    // only "today" has a known year, so only then movable holidays (Easter) can be shown
    fetchDay(date, { signal: controller.signal, year: isToday ? yearInPrague() : undefined })
      .then((data) => setState({ status: 'ready', nameDays: data.nameDays, holidays: data.holidays, cards: data.cards }))
      .catch((error) => {
        if (error?.name !== 'AbortError') setState({ status: 'error', message: error.message })
      })
    return () => controller.abort()
  }, [date, attempt, isToday])

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
        {/* sticky on desktop, never taller than the window: a low window scrolls the column itself */}
        <div className="pt-[calc(env(safe-area-inset-top)+1.25rem)] lg:sticky lg:top-0 lg:-mx-3 lg:max-h-dvh lg:self-start lg:overflow-y-auto lg:overscroll-contain lg:px-3 lg:pb-10 lg:pt-16">
          <DayHeader
            ref={titleRef}
            number={number}
            word={word}
            kicker={isToday ? 'Dnes · Tento den' : 'Tento den'}
            tagline="Deset výročí, která stojí za to znát – z Česka i ze světa."
            nameDays={<NameDays nameDays={state.status === 'ready' ? state.nameDays : null} country={country} onCountry={setCountry} />}
          />
          <DayNav date={date} />
        </div>
        <main className="mt-8 space-y-5 lg:mt-16">
          {state.status === 'loading' && <LoadingState />}
          {state.status === 'error' && <ErrorState message={state.message} onRetry={retry} />}
          {state.status === 'ready' && state.cards.length === 0 && (
            <EmptyState icon={CalendarX2} title="Pro tento den nic nemáme">Zkuste předchozí nebo další den.</EmptyState>
          )}
          {state.status === 'ready' && state.holidays.map((holiday) => <HolidayCard key={holiday.id} holiday={holiday} />)}
          {state.status === 'ready' && state.cards.map((card, index) => (
            <EventCard key={card.id} card={card} index={index} saved={favorites.isSaved(card.id)} onToggleFavorite={favorites.toggle} />
          ))}
        </main>
      </div>
    </div>
  )
}
