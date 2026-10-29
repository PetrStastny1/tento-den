import { CalendarDays, Heart } from 'lucide-react'
import { NavLink } from 'react-router'

// Floating glass capsule, iOS 26 style.
export function TabBar() {
  return (
    <nav
      aria-label="Hlavní navigace"
      className="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+14px)] z-30 flex justify-center px-4"
    >
      <div className="glass flex gap-1 rounded-full p-1.5">
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
      className={({ isActive }) => `inline-flex min-h-11 items-center gap-2 rounded-full px-5 font-rounded text-[15px] font-semibold motion-safe:transition-colors ${
        isActive ? 'bg-accent-wash text-accent-ink' : 'text-ink-soft hover:text-ink'
      }`}
    >
      <Icon className="size-5" aria-hidden="true" strokeWidth={2.2} />
      {label}
    </NavLink>
  )
}
