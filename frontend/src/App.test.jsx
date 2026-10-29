import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App.jsx'
import { card, dayResponse, holiday, stateDay } from './test/fixtures.js'

function renderAt(path) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <App />
    </MemoryRouter>,
  )
}

function mockFetch(response) {
  vi.stubGlobal('fetch', vi.fn(async () => response))
}

const jsonResponse = (data, ok = true, status = 200) => ({ ok, status, json: async () => data })

beforeEach(() => {
  vi.stubGlobal('IntersectionObserver', class {
    observe() {}
    disconnect() {}
  })
})

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  try {
    window.localStorage.clear()
  } catch {
    // ignore
  }
})

describe('Den', () => {
  it('ukáže deset karet dne od nejnovější, každou s celým textem a štítkem sekce', async () => {
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/10-28')

    const cards = await screen.findAllByRole('article')
    expect(cards).toHaveLength(10)
    expect(cards.map((article) => article.getAttribute('aria-label'))).toEqual(
      [10, 9, 8, 7, 6, 5, 4, 3, 2, 1].map((id) => `${1900 + id}, ${id <= 3 ? 'Česko' : 'Svět'}`),
    )
    expect(within(cards[0]).getByText('Událost číslo 10.')).toBeTruthy()
    expect(within(cards[0]).getByText('Svět')).toBeTruthy()
    expect(within(cards[9]).getByText('Česko')).toBeTruthy()
    expect(cards[0].querySelector('svg.lucide-globe, svg.lucide-map-pin')).toBeNull()
    expect(screen.getByRole('heading', { level: 1, name: '28. října' })).toBeTruthy()
    expect(fetch).toHaveBeenCalledWith('/api/day?date=10-28', expect.anything())
  })

  it('událost připomínaná státním svátkem je první, se štítkem a oficiálním názvem', async () => {
    const cards = [card(3, { year: 1918, yearLabel: '1918', stateDay }), ...[10, 9, 8, 7, 6, 5, 4, 2, 1].map((id) => card(id))]
    mockFetch(jsonResponse(dayResponse('10-28', cards)))
    renderAt('/den/10-28')

    const first = (await screen.findAllByRole('article'))[0]
    expect(within(first).getByText('Státní svátek')).toBeTruthy()
    expect(within(first).getByText('Den vzniku samostatného československého státu')).toBeTruthy()
    expect(within(first).getByText('Česko')).toBeTruthy()
  })

  it('svátek bez události má nahoře vlastní kartu s úvodem článku a atribucí, mimo desítku', async () => {
    mockFetch(jsonResponse(dayResponse('01-01', undefined, [holiday()])))
    renderAt('/den/01-01')

    const articles = await screen.findAllByRole('article')
    expect(articles).toHaveLength(11)
    const top = articles[0]
    expect(top.getAttribute('aria-label')).toBe('Ostatní svátek: Nový rok')
    expect(within(top).getByRole('heading', { name: 'Nový rok' })).toBeTruthy()
    expect(within(top).getByText('Nový rok je svátek začátku nového roku.')).toBeTruthy()
    expect(within(top).getByText('podle zákona č. 245/2000 Sb.')).toBeTruthy()
    const source = within(top).getByRole('link', { name: 'Zdroj' })
    expect(source.getAttribute('href')).toContain('oldid=26136929')
    expect(source.getAttribute('rel')).toBe('noopener noreferrer')
    expect(within(top).getByRole('link', { name: 'autoři' }).getAttribute('href')).toContain('action=history')
    expect(within(top).getByRole('link', { name: 'CC BY-SA 4.0' })).toBeTruthy()
    expect(within(top).queryByRole('link', { name: /zákon/ })).toBeNull() // links lead only to Wikipedia
  })

  it('dlouhý úvod svátku je celý v textu, jen složený na pět řádků s tlačítkem', async () => {
    const extract = 'Nový rok je svátek začátku nového roku. '.repeat(10).trim()
    mockFetch(jsonResponse(dayResponse('01-01', undefined, [holiday('Nový rok', { article: { ...holiday().article, extract } })])))
    renderAt('/den/01-01')

    const top = (await screen.findAllByRole('article'))[0]
    expect(within(top).getByText(extract).className).toContain('line-clamp-5')
    const toggle = within(top).getByRole('button', { name: 'Celý úvod' })
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
    fireEvent.click(toggle)
    expect(within(top).getByRole('button', { name: 'Méně' }).getAttribute('aria-expanded')).toBe('true')
    expect(within(top).getByText(extract).className).not.toContain('line-clamp-5')
  })

  it('rok (kvůli pohyblivým svátkům) se posílá jen pro dnešek', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-04-03T10:00:00Z'))
    try {
      mockFetch(jsonResponse(dayResponse('04-03')))
      renderAt('/')
      await screen.findAllByRole('article')
      expect(fetch.mock.calls[0][0]).toBe('/api/day?date=04-03&year=2026')
      cleanup()

      mockFetch(jsonResponse(dayResponse('04-06')))
      renderAt('/den/04-06')
      await screen.findAllByRole('article')
      expect(fetch.mock.calls[0][0]).toBe('/api/day?date=04-06')
    } finally {
      vi.useRealTimers()
    }
  })

  it('každá karta má atribuci: zdroj na revizi, autory a licenci', async () => {
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/10-28')

    for (const article of await screen.findAllByRole('article')) {
      const source = within(article).getByRole('link', { name: 'Zdroj' })
      expect(source.getAttribute('href')).toContain('oldid=26193496')
      expect(source.getAttribute('rel')).toBe('noopener noreferrer')
      expect(within(article).getByRole('link', { name: 'autoři' }).getAttribute('href')).toContain('action=history')
      expect(within(article).getByRole('link', { name: 'CC BY-SA 4.0' })).toBeTruthy()
    }
  })

  it('odkazy mimo Wikipedii nevykreslí jako odkaz a text nevkládá jako HTML', async () => {
    const evil = card(1, {
      text: '<img src=x onerror=alert(1)>',
      article: { title: 'Podvrh', url: 'https://evil.example.org/' },
    })
    mockFetch(jsonResponse(dayResponse('10-28', [evil])))
    renderAt('/den/10-28')

    const article = await screen.findByRole('article')
    expect(within(article).getByText('<img src=x onerror=alert(1)>')).toBeTruthy()
    expect(article.querySelector('img')).toBeNull()
    expect(within(article).queryByRole('link', { name: 'Podvrh' })).toBeNull()
  })

  it('při chybě ukáže chybový stav a umí to zkusit znovu', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValueOnce(jsonResponse(dayResponse())))
    renderAt('/den/10-28')

    const alert = await screen.findByRole('alert')
    expect(alert.textContent).toContain('Nepodařilo se spojit se serverem')
    fireEvent.click(within(alert).getByRole('button', { name: /Zkusit znovu/ }))
    expect(await screen.findAllByRole('article')).toHaveLength(10)
  })

  it('chyba serveru (HTTP 500) je chyba, ne prázdný den', async () => {
    mockFetch(jsonResponse(null, false, 500))
    renderAt('/den/10-28')
    expect((await screen.findByRole('alert')).textContent).toContain('HTTP 500')
  })

  it('prázdný den má vlastní stav', async () => {
    mockFetch(jsonResponse(dayResponse('02-29', [])))
    renderAt('/den/02-29')
    expect(await screen.findByText('Pro tento den nic nemáme')).toBeTruthy()
  })

  it('neplatné datum v adrese přesměruje na dnešek', async () => {
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/13-45')
    await waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(fetch.mock.calls[0][0]).toMatch(/^\/api\/day\?date=\d{2}-\d{2}&year=\d{4}$/) // today: with the year
    expect(fetch.mock.calls[0][0]).not.toContain('13-45')
  })
})

describe('Oblíbené', () => {
  it('fungují i bez localStorage (prohlížeč úložiště blokuje)', async () => {
    vi.spyOn(window, 'localStorage', 'get').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError')
    })
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/10-28')

    const first = (await screen.findAllByRole('article'))[0]
    fireEvent.click(within(first).getByRole('button', { name: 'Přidat do oblíbených' }))
    expect(within(first).getByRole('button', { name: 'Odebrat z oblíbených' })).toBeTruthy()

    fireEvent.click(screen.getByRole('link', { name: 'Oblíbené' }))
    expect(await screen.findByText(/oblíbené vydrží jen do zavření stránky/)).toBeTruthy()
    expect(screen.getAllByRole('article')).toHaveLength(1)
  })

  it('uloží kartu do localStorage a po načtení ji ukáže', async () => {
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/10-28')
    const first = (await screen.findAllByRole('article'))[0]
    fireEvent.click(within(first).getByRole('button', { name: 'Přidat do oblíbených' }))
    cleanup()

    renderAt('/oblibene')
    expect(screen.getAllByRole('article')).toHaveLength(1)
    expect(screen.getByText('Událost číslo 10.')).toBeTruthy()
    expect(JSON.parse(window.localStorage.getItem('tento-den:favorites'))[0].id).toBe(10)
  })
})
