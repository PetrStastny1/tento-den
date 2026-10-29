import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react'
import { useNavigate } from 'react-router'
import { formatDayTitle, fromDateInputValue, shiftDay, toDateInputValue, todayInPrague } from '../lib/dates.js'

export function DayNav({ date }) {
  const navigate = useNavigate()
  const isToday = date === todayInPrague()
  const go = (monthDay) => navigate(`/den/${monthDay}`)

  return (
    <nav aria-label="Listování dny" className="mt-3 flex items-center gap-2">
      <NavButton label={`Předchozí den (${formatDayTitle(shiftDay(date, -1))})`} onClick={() => go(shiftDay(date, -1))}>
        <ChevronLeft className="size-6" aria-hidden="true" />
      </NavButton>
      <button
        type="button"
        onClick={() => navigate('/')}
        disabled={isToday}
        className="min-h-11 rounded-full px-4 text-[15px] font-semibold text-orange-600 disabled:text-stone-400 dark:text-orange-400 dark:disabled:text-stone-600"
      >
        Dnes
      </button>
      <label className="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-stone-700 dark:text-stone-300">
        <CalendarDays className="size-6" aria-hidden="true" />
        <span className="sr-only">Vybrat datum</span>
        <input
          type="date"
          value={toDateInputValue(date)}
          onChange={(event) => {
            const monthDay = fromDateInputValue(event.target.value)
            if (monthDay) go(monthDay)
          }}
          className="absolute inset-0 cursor-pointer opacity-0"
        />
      </label>
      <span className="flex-1" />
      <NavButton label={`Další den (${formatDayTitle(shiftDay(date, 1))})`} onClick={() => go(shiftDay(date, 1))}>
        <ChevronRight className="size-6" aria-hidden="true" />
      </NavButton>
    </nav>
  )
}

function NavButton({ label, onClick, children }) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className="inline-flex size-11 items-center justify-center rounded-full bg-white text-stone-700 shadow-sm ring-1 ring-black/5 active:bg-stone-100 dark:bg-stone-900 dark:text-stone-200 dark:ring-white/10"
    >
      {children}
    </button>
  )
}
