// Only links to Wikipedia are rendered; anything else coming from data is dropped.
export function isWikipediaUrl(value) {
  try {
    const url = new URL(value)
    return url.protocol === 'https:' && (url.hostname === 'wikipedia.org' || url.hostname.endsWith('.wikipedia.org'))
  } catch {
    return false
  }
}

export function isCreativeCommonsUrl(value) {
  try {
    const url = new URL(value)
    return url.protocol === 'https:' && url.hostname === 'creativecommons.org'
  } catch {
    return false
  }
}
