export function card(id, overrides = {}) {
  return {
    id,
    date: '10-28',
    year: 1900 + id,
    yearLabel: String(1900 + id),
    section: id <= 3 ? 'cz' : 'world',
    text: `Událost číslo ${id}.`,
    sensitive: false,
    source: {
      title: '28. říjen',
      revisionUrl: 'https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&oldid=26193496',
      historyUrl: 'https://cs.wikipedia.org/w/index.php?title=28._%C5%99%C3%ADjen&action=history',
      license: 'CC BY-SA 4.0',
      licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.cs',
      fetchedAt: '2026-10-28T23:26:23+00:00',
    },
    article: { title: `Článek ${id}`, url: `https://cs.wikipedia.org/w/index.php?title=Clanek_${id}&oldid=1` },
    ...overrides,
  }
}

// ten cards, newest first – as the API returns them
export function dayResponse(date = '10-28', cards = [10, 9, 8, 7, 6, 5, 4, 3, 2, 1].map((id) => card(id)), holidays = [], nameDays = { cz: null, sk: null }) {
  return { date, nameDays, holidays, cards }
}

function source(wiki, title, revision) {
  return {
    title,
    wiki: `${wiki}.wikipedia.org`,
    revisionUrl: `https://${wiki}.wikipedia.org/w/index.php?title=${encodeURIComponent(title)}&oldid=${revision}`,
    historyUrl: `https://${wiki}.wikipedia.org/w/index.php?title=${encodeURIComponent(title)}&action=history`,
    license: 'CC BY-SA 4.0',
    licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.cs',
    fetchedAt: '2026-10-29T03:00:00+00:00',
  }
}

export const nameDays = {
  cz: {
    country: 'cz', label: 'Česko', language: 'cs', heading: 'Svátek má',
    names: [
      {
        name: 'Josef',
        note: null,
        meaning: { title: 'Josef', extract: 'Josef je mužské jméno hebrejského původu.', url: 'https://cs.wikipedia.org/w/index.php?title=Josef&oldid=1', source: source('cs', 'Josef', 1) },
        bearer: { name: 'Josef Čapek', wikidataId: 'Q213', url: 'https://cs.wikipedia.org/wiki/Josef_%C4%8Capek' },
      },
      { name: 'Josefa', note: null, meaning: null, bearer: null },
    ],
    otherNames: [{ name: 'Sibyla', note: null }],
    source: source('cs', '19. březen', 25754104),
  },
  sk: {
    country: 'sk', label: 'Slovensko', language: 'sk', heading: 'Meniny má',
    names: [{ name: 'Jozef', note: null, meaning: null, bearer: { name: 'Jozef Gabčík', wikidataId: 'Q1', url: 'https://sk.wikipedia.org/wiki/Jozef_Gab%C4%8D%C3%ADk' } }],
    otherNames: [],
    source: source('sk', '19. marec', 8184356),
  },
}

export const stateDay = {
  id: 'den-vzniku-samostatneho-ceskoslovenskeho-statu',
  name: 'Den vzniku samostatného československého státu',
  type: 'state',
  typeLabel: 'Státní svátek',
  law: 'zákon č. 245/2000 Sb.',
  lawUrl: 'https://www.zakonyprolidi.cz/cs/2000-245#p1',
}

export function holiday(name = 'Nový rok', overrides = {}) {
  return {
    date: '01-01',
    id: 'novy-rok',
    name,
    type: 'other',
    typeLabel: 'Ostatní svátek',
    law: 'zákon č. 245/2000 Sb.',
    lawUrl: 'https://www.zakonyprolidi.cz/cs/2000-245#p2',
    article: {
      title: name,
      extract: `${name} je svátek začátku nového roku.`,
      url: 'https://cs.wikipedia.org/w/index.php?title=Nov%C3%BD_rok&oldid=26136929',
      source: {
        title: name,
        revisionUrl: 'https://cs.wikipedia.org/w/index.php?title=Nov%C3%BD_rok&oldid=26136929',
        historyUrl: 'https://cs.wikipedia.org/w/index.php?title=Nov%C3%BD_rok&action=history',
        license: 'CC BY-SA 4.0',
        licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/deed.cs',
        fetchedAt: '2026-10-29T02:10:00+00:00',
      },
    },
    ...overrides,
  }
}
