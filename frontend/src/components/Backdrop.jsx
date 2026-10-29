// Soft light in the colour of the day behind everything; purely decorative.
export function Backdrop() {
  return (
    <div aria-hidden="true" className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <div className="aurora absolute -inset-[15%] motion-safe:animate-drift" />
    </div>
  )
}
