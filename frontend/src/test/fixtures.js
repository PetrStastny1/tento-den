export function card(id, overrides = {}) {
  return {
    id,
    date: '10-28',
    year: 1900 + id,
    yearLabel: String(1900 + id),
    section: id <= 2 ? 'cz' : 'world',
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

export function dayResponse(date = '10-28', cards = [1, 2, 3, 4, 5].map((id) => card(id))) {
  return { date, cards }
}
