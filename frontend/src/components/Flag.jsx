// Small flags as SVG (emoji flags are not shown on Windows). Decorative: the button has a label.
export function Flag({ country, className = '' }) {
  return (
    <svg viewBox="0 0 30 20" className={`rounded-[3px] ring-1 ring-hairline ${className}`} aria-hidden="true" focusable="false">
      {country === 'sk' ? (
        <>
          <rect width="30" height="20" fill="#fff" />
          <rect y="6.67" width="30" height="6.67" fill="#0b4ea2" />
          <rect y="13.33" width="30" height="6.67" fill="#ee1c25" />
          {/* simplified coat of arms: red shield, white double cross, blue hills */}
          <path d="M6.2 4.6h9.6v6.6c0 3.1-2.4 5.2-4.8 6.2-2.4-1-4.8-3.1-4.8-6.2z" fill="#ee1c25" stroke="#fff" strokeWidth="0.7" />
          <path d="M10.4 6h1.2v1.4h2.2v1h-2.2v1.4h2.8v1h-2.8v2.4h-1.2v-2.4H7.6v-1h2.8V8.4H8.2v-1h2.2z" fill="#fff" />
          <path d="M7 14.6c0.9-1.3 2-1.6 2.8-1 0.5-0.9 1.9-0.9 2.4 0 0.8-0.6 1.9-0.3 2.8 1-1.1 1.3-2.5 2.2-4 2.8-1.5-0.6-2.9-1.5-4-2.8z" fill="#0b4ea2" />
        </>
      ) : (
        <>
          <rect width="30" height="10" fill="#fff" />
          <rect y="10" width="30" height="10" fill="#d7141a" />
          <path d="M0 0l15 10L0 20z" fill="#11457e" />
        </>
      )}
    </svg>
  )
}
