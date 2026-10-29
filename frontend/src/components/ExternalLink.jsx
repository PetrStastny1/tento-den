import { isCreativeCommonsUrl, isWikipediaUrl } from '../lib/links.js'

// Renders a link only to Wikipedia (or the license); otherwise plain text.
export function ExternalLink({ href, license = false, plain = false, className = '', children }) {
  const allowed = license ? isCreativeCommonsUrl(href) : isWikipediaUrl(href)
  if (!allowed) return <span className={className}>{children}</span>
  const underline = plain ? '' : 'underline decoration-1 underline-offset-2 hover:decoration-2'
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" className={`${underline} ${className}`}>
      {children}
    </a>
  )
}
