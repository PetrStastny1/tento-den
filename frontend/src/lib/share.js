// Web Share API on iPhone, otherwise the link goes to the clipboard.
export async function shareCard(card, url) {
  const text = `${card.yearLabel}: ${card.text}`
  if (typeof navigator.share === 'function') {
    try {
      await navigator.share({ title: 'Tento den', text, url })
      return 'shared'
    } catch (error) {
      if (error?.name === 'AbortError') return 'cancelled'
    }
  }
  try {
    await navigator.clipboard.writeText(`${text}\n${url}`)
    return 'copied'
  } catch {
    return 'failed'
  }
}

export function cardUrl(card) {
  const base = new URL(import.meta.env.BASE_URL, window.location.origin)
  return new URL(`den/${card.date}#karta-${card.id}`, base).toString()
}
