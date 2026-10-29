// @vitest-environment node
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

// The texts a link preview and the installed app show must describe the app as it is now.
const index = readFileSync(new URL('../index.html', import.meta.url), 'utf8')
const manifest = JSON.parse(readFileSync(new URL('../public/manifest.webmanifest', import.meta.url), 'utf8'))
const meta = (attribute, name) => new RegExp(`<meta ${attribute}="${name}" content="([^"]+)"`).exec(index)?.[1]

describe('metadata', () => {
  it('popis i náhled odkazu odpovídají aktuálnímu stavu – deset výjimečných událostí, jmeniny, státní svátky', () => {
    for (const text of [meta('name', 'description'), meta('property', 'og:description'), meta('name', 'twitter:description'), manifest.description]) {
      expect(text).toContain('deset výjimečných událostí')
      expect(text).not.toMatch(/Wikipedi/) // the source is credited on every card, not in the description
      expect(text).toMatch(/jmenin/)
      expect(text).toMatch(/státními svátky/)
      expect(text).not.toMatch(/pět|five/i)
    }
  })

  it('úplné Open Graph značky s absolutními adresami pod /tento-den/ a obrázkem 1200 × 630', () => {
    expect(meta('property', 'og:title')).toBe('Tento den')
    expect(meta('property', 'og:type')).toBe('website')
    expect(meta('property', 'og:locale')).toBe('cs_CZ')
    expect(meta('property', 'og:url')).toBe('https://petrstastny1.github.io/tento-den/')
    expect(meta('property', 'og:image')).toBe('https://petrstastny1.github.io/tento-den/og-image.png')
    expect([meta('property', 'og:image:width'), meta('property', 'og:image:height')]).toEqual(['1200', '630'])
    expect(meta('property', 'og:image:alt')).toContain('deset výjimečných událostí')
    const png = readFileSync(new URL('../public/og-image.png', import.meta.url))
    expect([png.readUInt32BE(16), png.readUInt32BE(20)]).toEqual([1200, 630]) // PNG IHDR width and height
  })
})
