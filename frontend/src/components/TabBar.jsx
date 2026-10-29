import { CalendarDays, Heart } from 'lucide-react'
import { NavLink } from 'react-router'

export function TabBar() {
  return (
    <nav aria-label="Hlavní navigace" className="fixed inset-x-0 bottom-0 z-20 border-t border-black/10 pb-[env(safe-area-inset-bottom)] bar-surface dark:border-white/10">
      <div className="mx-auto flex max-w-2xl">
        <Tab to="/" end icon={CalendarDays} label="Dnes" />
        <Tab to="/oblibene" icon={Heart} label="Oblíbené" />
      </div>
    </nav>
  )
}

function Tab({ to, end, icon: Icon, label }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) => `flex min-h-12 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-medium ${
        isActive ? 'text-orange-600 dark:text-orange-400' : 'text-stone-500 dark:text-stone-400'
      }`}
    >
      <Icon className="size-6" aria-hidden="true" />
      {label}
    </NavLink>
  )
}
