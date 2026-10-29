import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App.jsx'
import { card, dayResponse } from './test/fixtures.js'

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
  it('ukáže pět karet dne', async () => {
    mockFetch(jsonResponse(dayResponse()))
    renderAt('/den/10-28')

    const cards = await screen.findAllByRole('article')
    expect(cards).toHaveLength(5)
    expect(screen.getByRole('heading', { level: 1, name: '28. října' })).toBeTruthy()
    expect(fetch).toHaveBeenCalledWith('/api/day?date=10-28', expect.anything())
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
    expect(await screen.findAllByRole('article')).toHaveLength(5)
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
    expect(fetch.mock.calls[0][0]).toMatch(/^\/api\/day\?date=\d{2}-\d{2}$/)
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
    expect(screen.getByText('Událost číslo 1.')).toBeTruthy()
    expect(JSON.parse(window.localStorage.getItem('tento-den:favorites'))[0].id).toBe(1)
  })
})
