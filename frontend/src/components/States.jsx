import { CloudOff, RotateCw } from 'lucide-react'

export function LoadingState() {
  return (
    <div role="status" aria-label="Načítám" className="space-y-5">
      {[0, 1, 2].map((index) => (
        <div key={index} className="relative h-56 overflow-hidden rounded-[28px] bg-card ring-1 ring-hairline">
          <div className="shimmer absolute inset-0 motion-safe:animate-shimmer" />
          <div className="m-6 h-12 w-28 rounded-xl bg-accent-wash" />
          <div className="mx-6 h-4 rounded-full bg-hairline" />
          <div className="mx-6 mt-3 h-4 w-2/3 rounded-full bg-hairline" />
        </div>
      ))}
    </div>
  )
}

export function ErrorState({ message, onRetry }) {
  return (
    <div role="alert" className="glass rounded-[28px] p-8 text-center">
      <CloudOff className="mx-auto size-10 text-ink-faint" aria-hidden="true" />
      <p className="mt-3 font-serif text-[22px] font-semibold">Něco se pokazilo</p>
      <p className="mt-1 text-ink-soft">{message}</p>
      <button
        type="button"
        onClick={onRetry}
        className="mt-5 inline-flex min-h-11 items-center gap-2 rounded-full bg-ink px-5 font-rounded font-semibold text-paper motion-safe:transition-transform active:scale-95"
      >
        <RotateCw className="size-5" aria-hidden="true" /> Zkusit znovu
      </button>
    </div>
  )
}

export function EmptyState({ icon: Icon, title, children }) {
  return (
    <div className="glass rounded-[28px] p-8 text-center">
      {Icon && <Icon className="mx-auto size-10 text-accent-ink" aria-hidden="true" />}
      <p className="mt-3 font-serif text-[22px] font-semibold">{title}</p>
      <p className="mt-1 text-ink-soft">{children}</p>
    </div>
  )
}
