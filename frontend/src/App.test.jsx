import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App.jsx'
import { card, dayResponse, holiday, nameDays, stateDay } from './test/fixtures.js'

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

  it('kulaté výročí velké události je nahoře se štítkem „500 let“', async () => {
    const cards = [card(1, { year: 1526, yearLabel: '1526', anniversary: { years: 500, label: '500 let' } }), ...[10, 9, 8, 7, 6, 5, 4, 3, 2].map((id) => card(id))]
    mockFetch(jsonResponse(dayResponse('08-29', cards)))
    renderAt('/den/08-29')
    const articles = await screen.findAllByRole('article')
    expect(within(articles[0]).getByText('500 let')).toBeTruthy()
    expect(articles.slice(1).some((article) => /\d+ let$/.test(article.querySelector('span.bg-accent')?.textContent ?? ''))).toBe(false)
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

  it('všechny karty vypadají stejně, liší se jen štítkem Česko / Svět', async () => {
    const cards = [card(10, { sensitive: true }), card(2, { sensitive: false })] // world sensitive, Czech not
    mockFetch(jsonResponse(dayResponse('10-28', cards)))
    renderAt('/den/10-28')

    const [world, czech] = await screen.findAllByRole('article')
    const look = (article) => article.innerHTML.replace(/Svět|Česko/g, 'SEKCE').replace(/\d{4}|Událost číslo \d+\.|Článek \d+|Clanek_\d+|karta-\d+|před \d+ lety/g, 'X')
    expect(look(world)).toBe(look(czech))
    expect(within(world).getByText('Svět')).toBeTruthy()
    expect(within(czech).getByText('Česko')).toBeTruthy()
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

const nameButtons = (block) => within(block).queryAllByRole('button').filter((b) => b.getAttribute('aria-haspopup') === 'dialog').map((b) => b.textContent)
const sentence = (block) => block.querySelector('p').textContent

describe('Jmeniny', () => {
  it('jedna věta, výchozí Česko; vlaječka přepne na slovenské meniny, události se nemění, volba se pamatuje', async () => {
    mockFetch(jsonResponse(dayResponse('03-19', undefined, [], nameDays)))
    renderAt('/den/03-19')

    const block = await screen.findByRole('region', { name: 'Jmeniny' })
    expect(sentence(block)).toBe('Svátek má Josef a Josefa')
    expect(block.textContent).not.toContain('Sibyla') // the card is one sentence
    expect(block.textContent).not.toMatch(/Zdroj|autoři|CC BY-SA/) // attribution only in the panel
    const group = within(block).getByRole('radiogroup', { name: 'Jmeniny: Česko / Slovensko' })
    expect(within(group).getByRole('radio', { name: 'Česko' }).getAttribute('aria-checked')).toBe('true')
    expect(group.querySelector('svg')).toBeTruthy() // SVG flags, not emoji
    const cards = screen.getAllByRole('article').map((article) => article.textContent)

    fireEvent.click(within(group).getByRole('radio', { name: 'Slovensko' }))
    const slovak = screen.getByRole('region', { name: 'Meniny' })
    expect(sentence(slovak)).toBe('Meniny má Jozef')
    expect(screen.getAllByRole('article').map((article) => article.textContent)).toEqual(cards)
    expect(window.localStorage.getItem('tento-den:name-days')).toBe('sk')
    expect(fetch).toHaveBeenCalledTimes(1) // switching does not reload the day
    cleanup()

    mockFetch(jsonResponse(dayResponse('03-19', undefined, [], nameDays)))
    renderAt('/den/03-19')
    expect(sentence(await screen.findByRole('region', { name: 'Meniny' }))).toBe('Meniny má Jozef')
  })

  it('jen jméno s významem nebo nositelem je klepací; panel ukáže jméno, význam, nositele a atribuci, zavře se Esc i klepnutím mimo', async () => {
    mockFetch(jsonResponse(dayResponse('03-19', undefined, [], nameDays)))
    renderAt('/den/03-19')

    const block = await screen.findByRole('region', { name: 'Jmeniny' })
    expect(nameButtons(block)).toEqual(['Josef']) // Josefa has neither meaning nor bearer: plain text
    const josef = within(block).getByRole('button', { name: 'Josef' })
    fireEvent.click(josef)
    expect(josef.getAttribute('aria-expanded')).toBe('true')
    const panel = screen.getByRole('dialog', { name: 'Josef' })
    expect(within(panel).getByText('Josef je mužské jméno hebrejského původu.')).toBeTruthy()
    expect(within(panel).getByRole('link', { name: /Josef Čapek/ }).getAttribute('href')).toBe('https://cs.wikipedia.org/wiki/Josef_%C4%8Capek')
    const source = within(panel).getByRole('link', { name: 'Zdroj' })
    expect(source.getAttribute('href')).toContain('cs.wikipedia.org')
    expect(source.getAttribute('rel')).toBe('noopener noreferrer')
    expect(within(panel).getByRole('link', { name: 'autoři' })).toBeTruthy()
    expect(within(panel).getByRole('link', { name: 'CC BY-SA 4.0' })).toBeTruthy()

    fireEvent.keyDown(document, { key: 'Escape' })
    expect(screen.queryByRole('dialog')).toBeNull()
    expect(document.activeElement).toBe(josef)

    fireEvent.click(josef)
    fireEvent.pointerDown(document.body)
    expect(screen.queryByRole('dialog')).toBeNull()
  })

  it('více jmen: každé má vlastní panel s vlastním významem a nositelem, nikdy dva panely naráz (CZ i SK)', async () => {
    const person = (name, wiki) => ({
      name,
      note: null,
      meaning: { title: name, extract: `${name}: význam.`, url: `https://${wiki}.wikipedia.org/w/index.php?title=${name}&oldid=1`, source: { title: name, wiki: `${wiki}.wikipedia.org`, revisionUrl: `https://${wiki}.wikipedia.org/w/index.php?title=${name}&oldid=1`, historyUrl: `https://${wiki}.wikipedia.org/w/index.php?title=${name}&action=history`, license: 'CC BY-SA 4.0', licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.cs', fetchedAt: '2026-10-29T03:00:00+00:00' } },
      bearer: { name: `${name} Nositel`, wikidataId: 'Q1', url: `https://${wiki}.wikipedia.org/wiki/${name}_Nositel` },
    })
    const days = {
      cz: { ...nameDays.cz, names: [person('Ondřej', 'cs'), person('Andrej', 'cs')], otherNames: [] },
      sk: { ...nameDays.sk, names: [person('Andrej', 'sk'), person('Ondrej', 'sk'), person('Andreas', 'sk')] },
    }
    mockFetch(jsonResponse(dayResponse('11-30', undefined, [], days)))
    renderAt('/den/11-30')

    const block = await screen.findByRole('region', { name: 'Jmeniny' })
    expect(sentence(block)).toBe('Svátek má Ondřej a Andrej')
    fireEvent.click(within(block).getByRole('button', { name: 'Ondřej' }))
    expect(within(screen.getByRole('dialog', { name: 'Ondřej' })).getByText('Ondřej: význam.')).toBeTruthy()
    fireEvent.click(within(block).getByRole('button', { name: 'Andrej' }))
    expect(screen.getAllByRole('dialog')).toHaveLength(1)
    const andrej = screen.getByRole('dialog', { name: 'Andrej' })
    expect(within(andrej).getByText('Andrej: význam.')).toBeTruthy()
    expect(within(andrej).getByRole('link', { name: /Andrej Nositel/ }).getAttribute('href')).toBe('https://cs.wikipedia.org/wiki/Andrej_Nositel')

    fireEvent.click(within(block).getByRole('radio', { name: 'Slovensko' }))
    expect(screen.queryByRole('dialog')).toBeNull()
    const slovak = screen.getByRole('region', { name: 'Meniny' })
    expect(sentence(slovak)).toBe('Meniny má Andrej, Ondrej a Andreas')
    for (const name of ['Andrej', 'Ondrej', 'Andreas']) {
      fireEvent.click(within(slovak).getByRole('button', { name }))
      const panel = screen.getByRole('dialog', { name })
      expect(within(panel).getByText(`${name}: význam.`)).toBeTruthy()
      expect(within(panel).getByRole('link', { name: new RegExp(`${name} Nositel`) }).getAttribute('href')).toBe(`https://sk.wikipedia.org/wiki/${name}_Nositel`)
      expect(screen.getAllByRole('dialog')).toHaveLength(1)
    }
  })

  it('varianta: „Sylva – varianta jména Silvie“, význam uvedený jako význam jména Silvie, nositel jen pro tvar Sylva', async () => {
    const silvieMeaning = { ...nameDays.cz.names[0].meaning, title: 'Silvie', aboutName: 'Silvie', extract: 'Silvie je ženské jméno.' }
    const day = {
      ...nameDays.cz,
      names: [
        { name: 'Silvie', note: null, variantOf: null, meaning: silvieMeaning, bearer: { name: 'Silvie Maryško', wikidataId: 'Q1', url: 'https://cs.wikipedia.org/wiki/Silvie_Mary%C5%A1ko' } },
        { name: 'Silvána', note: null, variantOf: null, meaning: null, bearer: null },
        { name: 'Sylva', note: null, variantOf: { name: 'Silvie', rule: 'a' }, meaning: silvieMeaning, bearer: null },
      ],
      otherNames: [],
    }
    mockFetch(jsonResponse(dayResponse('10-29', undefined, [], { cz: day, sk: null })))
    renderAt('/den/10-29')

    const block = await screen.findByRole('region', { name: 'Jmeniny' })
    expect(nameButtons(block)).toEqual(['Silvie', 'Sylva']) // Silvána: plain text
    fireEvent.click(within(block).getByRole('button', { name: 'Sylva' }))
    const panel = screen.getByRole('dialog', { name: 'Sylva' })
    expect(panel.textContent).toContain('Sylva – varianta jména Silvie')
    expect(panel.textContent).toContain('Význam jména Silvie')
    expect(within(panel).queryByText(/Známý nositel/)).toBeNull()
    fireEvent.click(within(block).getByRole('button', { name: 'Silvie' }))
    const main = screen.getByRole('dialog', { name: 'Silvie' })
    expect(main.textContent).not.toContain('varianta')
    expect(main.textContent).toContain('Význam a původ')
  })

  it('29. 10. ukáže Silvii (CZ)', async () => {
    const silvie = { ...nameDays.cz, names: [{ name: 'Silvie', note: null, meaning: null, bearer: null }, { name: 'Silvána', note: null, meaning: null, bearer: null }, { name: 'Sylva', note: null, meaning: null, bearer: null }], otherNames: [] }
    mockFetch(jsonResponse(dayResponse('10-29', undefined, [], { cz: silvie, sk: null })))
    renderAt('/den/10-29')
    expect(sentence(await screen.findByRole('region', { name: 'Jmeniny' }))).toBe('Svátek má Silvie, Silvána a Sylva')
  })

  it('den bez jmenin a bez státního svátku nemá žádnou hlášku ani kartu svátku', async () => {
    mockFetch(jsonResponse(dayResponse('09-13')))
    renderAt('/den/09-13')
    await screen.findAllByRole('article')
    expect(screen.queryByRole('region', { name: 'Jmeniny' })).toBeNull()
    expect(document.body.textContent).not.toMatch(/nikdo|nemá svátek|nemá meniny/)
    expect(screen.queryByText(/Státní svátek|Ostatní svátek|Významný den/)).toBeNull()
  })

  it('bez localStorage je výchozí Česko a přepínač funguje', async () => {
    vi.spyOn(window, 'localStorage', 'get').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError')
    })
    mockFetch(jsonResponse(dayResponse('03-19', undefined, [], nameDays)))
    renderAt('/den/03-19')

    const block = await screen.findByRole('region', { name: 'Jmeniny' })
    fireEvent.click(within(block).getByRole('radio', { name: 'Slovensko' }))
    expect(sentence(screen.getByRole('region', { name: 'Meniny' }))).toBe('Meniny má Jozef')
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
