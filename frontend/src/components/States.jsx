import { RotateCw } from 'lucide-react'

export function LoadingState() {
  return (
    <div role="status" aria-label="Načítám" className="space-y-4">
      {[0, 1, 2].map((index) => (
        <div key={index} className="h-40 rounded-3xl bg-stone-200/70 motion-safe:animate-pulse dark:bg-stone-800/70" />
      ))}
    </div>
  )
}

export function ErrorState({ message, onRetry }) {
  return (
    <div role="alert" className="rounded-3xl bg-white p-6 text-center shadow-sm ring-1 ring-black/5 dark:bg-stone-900 dark:ring-white/10">
      <p className="text-[17px] font-semibold">Něco se pokazilo</p>
      <p className="mt-1 text-stone-600 dark:text-stone-400">{message}</p>
      <button type="button" onClick={onRetry} className="mt-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-orange-600 px-5 font-semibold text-white active:bg-orange-700">
        <RotateCw className="size-5" aria-hidden="true" /> Zkusit znovu
      </button>
    </div>
  )
}

export function EmptyState({ title, children }) {
  return (
    <div className="rounded-3xl bg-white p-6 text-center shadow-sm ring-1 ring-black/5 dark:bg-stone-900 dark:ring-white/10">
      <p className="text-[17px] font-semibold">{title}</p>
      <p className="mt-1 text-stone-600 dark:text-stone-400">{children}</p>
    </div>
  )
}
