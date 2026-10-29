import { CalendarDays, ChevronLeft, ChevronRight, Sparkles } from 'lucide-react'
import { useNavigate } from 'react-router'
import { formatDayTitle, fromDateInputValue, shiftDay, toDateInputValue, todayInPrague } from '../lib/dates.js'

export function DayNav({ date }) {
  const navigate = useNavigate()
  const isToday = date === todayInPrague()
  const go = (monthDay) => navigate(`/den/${monthDay}`)

  return (
    <nav aria-label="Listování dny" className="mt-6 flex flex-wrap items-center gap-3">
      <div className="glass flex items-center rounded-full p-1">
        <RoundButton label={`Předchozí den (${formatDayTitle(shiftDay(date, -1))})`} onClick={() => go(shiftDay(date, -1))}>
          <ChevronLeft className="size-6" aria-hidden="true" strokeWidth={2.2} />
        </RoundButton>
        <label className="relative inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full px-3 font-rounded text-[15px] font-semibold text-ink-soft focus-within:outline-3 focus-within:outline-offset-2 focus-within:outline-accent hover:bg-accent-wash">
          <CalendarDays className="size-5" aria-hidden="true" />
          <span>Vybrat den</span>
          <input
            type="date"
            aria-label="Vybrat datum"
            value={toDateInputValue(date)}
            onChange={(event) => {
              const monthDay = fromDateInputValue(event.target.value)
              if (monthDay) go(monthDay)
            }}
            className="absolute inset-0 cursor-pointer opacity-0"
          />
        </label>
        <RoundButton label={`Další den (${formatDayTitle(shiftDay(date, 1))})`} onClick={() => go(shiftDay(date, 1))}>
          <ChevronRight className="size-6" aria-hidden="true" strokeWidth={2.2} />
        </RoundButton>
      </div>
      {!isToday && (
        <button
          type="button"
          onClick={() => navigate('/')}
          className="inline-flex min-h-11 items-center gap-1.5 rounded-full bg-ink px-4 font-rounded text-[15px] font-semibold text-paper shadow-lg motion-safe:transition-transform active:scale-95"
        >
          <Sparkles className="size-4" aria-hidden="true" /> Dnes
        </button>
      )}
    </nav>
  )
}

function RoundButton({ label, onClick, children }) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className="inline-flex size-11 items-center justify-center rounded-full text-ink hover:bg-accent-wash motion-safe:transition active:scale-90"
    >
      {children}
    </button>
  )
}
