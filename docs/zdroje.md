# Zdroje dat – průzkum (krok 2)

Průzkum proběhl 28. 10. 2026 skutečnými voláními API. Ukázkové odpovědi jsou
v [backend/tests/fixtures/wikimedia/](../backend/tests/fixtures/wikimedia/) (CC BY-SA 4.0,
viz README tamtéž). U každého tvrzení je uvedeno volání nebo dokumentace, ze které vychází.

## Shrnutí a doporučení

| Účel | Zdroj | Proč |
|---|---|---|
| **Události Česko i Svět** (text, rok, odkazy) | stránka dne na cs.wiki („28. říjen“) jako Parsoid HTML: `GET https://cs.wikipedia.org/api/rest_v1/page/html/{den}` | jediný zdroj, který odděluje **Česko** a **Svět**; HTML má vyřešené šablony a reference |
| Revize a úvody odkazovaných článků | feed `GET https://cs.wikipedia.org/api/rest_v1/feed/onthisday/events/{MM}/{DD}` | u každého odkazovaného článku vrací `revision`, `extract`, `wikibase_item`, `description` – bez dalších dotazů |
| Signál „selected“ | feed `…/onthisday/selected/{MM}/{DD}` (= stránka *Wikipedie:Vybraná výročí dne/{den}*) | redakční výběr 5 výročí na den |
| Délka, ID revize, Wikidata ID | Action API `prop=info\|pageprops`, 50 titulů na dotaz | hromadně |
| Jazykové verze | Wikidata `wbgetentities&props=sitelinks`, 50 položek na dotaz | 1 dotaz na 50 článků (cs `prop=langlinks` potřeboval 8 dotazů na 50 článků) |
| Zobrazení | Action API `prop=pageviews` (PageViewInfo), **60 dní**, dávkově | 90 dní umí jen REST Pageviews API, ale 1 dotaz = 1 článek |

**Pro svět i pro Česko** tedy použijeme **českou stránku dne**. Anglický feed by pro verzi 1
(jen čeština) znamenal překládat text, což zadání zakazuje. Anglické „selected“ může sloužit jako
další signál přes Wikidata ID (viz signály).

## 1. Wikimedia REST API „On this day“

**Endpoint:** `GET https://{lang}.wikipedia.org/api/rest_v1/feed/onthisday/{type}/{MM}/{DD}`
(funguje i `https://api.wikimedia.org/feed/v1/wikipedia/{lang}/onthisday/{type}/{MM}/{DD}` –
stejná odpověď; jeho dokumentace ale byla přesunuta, `api.wikimedia.org/wiki/Feed_API/…` dnes
přesměrovává na [Wikimedia APIs](https://www.mediawiki.org/wiki/Wikimedia_APIs)).

**Typy:** `selected`, `events`, `births`, `deaths`, `holidays`, `all`. Ověřeno voláním: `all`
pro 29. 2. vrátil `{selected: 5, births: 70, deaths: 28, events: 6, holidays: 2}` o velikosti
469 KiB – dva samostatné dotazy `events` + `selected` jsou úspornější.

**Jazyky** (volání `GET https://wikimedia.org/api/rest_v1/feed/availability`, klíč `on_this_day`):
ar, bs, **cs**, de, en, es, fr, it, pt, ru, sv, tr, uk, zh. **Čeština je podporovaná.**

Testovací volání pro 28. 10. (200 OK u všech):

| Jazyk | `selected` | `events` |
|---|---|---|
| cs | 5 položek (20 KiB) | 31 položek (228 KiB) |
| en | 17 položek (170 KiB) | 59 položek (373 KiB) |
| de | – (52 KiB) | – (624 KiB) |

Ukázka položky `events` (cs, zkráceno):

```json
{
  "text": "V řadě měst protektorátu Čechy a Morava proběhly protiněmecké demonstrace. V Praze byl při nich smrtelně zraněn medik Jan Opletal a dělník Václav Sedláček.",
  "year": 1939,
  "pages": [{
    "title": "Protesty_proti_nacistické_okupaci_v_Protektorátu_Čechy_a_Morava_28._října_1939",
    "pageid": 1230142,
    "wikibase_item": "Q26936104",
    "revision": "26009129",
    "timestamp": "2026-07-04T14:03:19Z",
    "extract": "Protesty proti nacistické okupaci dne 28. října 1939 v Protektorátu Čechy a Morava se proměnily v mohutnou národní demonstraci, …",
    "content_urls": { "desktop": {
      "page": "https://cs.wikipedia.org/wiki/Protesty_proti_…",
      "revisions": "https://cs.wikipedia.org/wiki/Protesty_proti_…?action=history" } }
  }]
}
```

**Zjištění:**
- cs feed **slévá Česko a Svět do jednoho seznamu** (seřazeno podle roku sestupně) – sekci nevrací.
  Proto potřebujeme stránku dne.
- Odkud data bere: podle konfigurace služby wikifeeds
  ([on-this-day.languages.js](https://github.com/wikimedia/mediawiki-services-wikifeeds/blob/master/lib/on-this-day.languages.js))
  parsuje cs stránky `{D}._{měsíc}` (sekce „Události“) a pro `selected`
  `Wikipedie:Vybraná_výročí_dne/{D}._{měsíc}`.
- `pages[]` nese **aktuální revizi** každého odkazovaného článku: pro *Vznik Československa*
  feed vrátil `revision: "26271507"`, stejně jako `lastrevid` z Action API i `etag` z page/summary.
  Pozor: ve feedu je `revision` **řetězec**, v Action API číslo.
- `in_the_news` ani `did_you_know` **cs nepodporují** (availability) – pro budoucí aktuality
  a „Víte, že…“ budeme české stránky parsovat sami.
- Cache: `cache-control: s-maxage=300, max-age=60`.

## 2. Česká Wikipedie – stránky dnů

**Získání sekce:**
- Parsoid HTML: `GET https://cs.wikipedia.org/api/rest_v1/page/html/28._%C5%99%C3%ADjen`
  – 1 dotaz na den, ID revize v hlavičce `content-revision-id: 26193496` (a v `etag`).
- Struktura sekcí: `GET /w/api.php?action=parse&page=28._říjen&prop=sections` →
  `1 Události / 2 Česko / 3 Svět / 4 Narození / …`
- Wikitext hromadně: `action=query&prop=revisions&rvprop=ids|timestamp|content&rvslots=main&titles=…`
  – **všech 366 stránek dnů v 8 dotazech** (50 titulů na dotaz).

Ukázka HTML (28. říjen, zkráceno):

```html
<section data-mw-section-id="2"><h3 id="Česko">Česko</h3>
<ul><li><a rel="mw:WikiLink" href="./1939" title="1939">1939</a> – V řadě měst
  <a rel="mw:WikiLink" href="./Protektorát_Čechy_a_Morava" title="Protektorát Čechy a Morava">protektorátu Čechy a Morava</a>
  proběhly protiněmecké <a rel="mw:WikiLink" … title="Protesty proti nacistické okupaci v Protektorátu Čechy a Morava 28. října 1939">demonstrace</a>. …</li>
```

**Je sekce rozdělená na Česko a Svět?** Ano – ověřeno na **všech 366 stránkách** (wikitext):

| Struktura podsekcí „Události“ | Počet dní |
|---|---|
| Česko, Svět | 343 |
| text před první podsekcí + Česko, Svět | 18 |
| navíc „Pravidelné události“ / „Hospodářství a ekologie“ | 2 |
| jen Česko (Svět s komentářem na řádku nadpisu – 22. 5.) | 1 |
| Svět dále dělený podle století (úroveň `====`) | 2 |

**Počty událostí** (včetně podbodů, před filtry; celkem 9 360 za rok):

| | min | medián | max | dní s < 2 | dní s < 3 |
|---|---|---|---|---|---|
| Česko | 1 (8. 12., 26. 12.) | 7 | 40 | 2 | 8 |
| Svět | 3 (29. 2.) | 15 | 97 | 0 | 0 |

**Formát řádků není jednotný** (8 272 řádků má standardní tvar `* [[rok]] – text`):

| Varianta | Příklad | Výskyt |
|---|---|---|
| rok s podbody | `* [[1918]]` + `** V Praze byl …` | 338 × |
| výplňová šablona | `* {{0}}{{0}}[[69]] – …` (7. 2., 5. 1.) | stovky |
| rok bez odkazu | `* 1099 – …` (celý 15. 8.) | – |
| rok jako odkaz jinam | `* [[1984 v dopravě\|1984]] – …` | – |
| před naším letopočtem | `* [[45 př. n. l.]] – …` | 185 × |
| jiný oddělovač / žádný | `'''–'''`, `* [[1910]] Vzducholoď …` | 6 × |
| řádek bez roku | `* [[Tříkrálová sbírka]] … (každoročně od roku 2001)` | 5 × |
| podbody bez roku | `** zaniká …` (31. 12.) | 3 × |
| zakomentované události | `<!-- * [[1888]] – … -->` | 4 × |
| reference uvnitř řádku | `<ref>{{Citace …}}</ref>` přes více řádků | časté |

**Rok a článek z textu:** v Parsoid HTML stačí vzít `<li>` v `<section>` pod `h3#Česko` / `h3#Svět`,
rok z úvodu textu (`^(\d{1,4})( př. n. l.)? – `), u podbodů rok rodiče; odkazy z
`<a rel="mw:WikiLink" title="…">`. Šablony, komentáře i reference jsou v HTML už vyřešené
(reference jsou `<sup class="mw-ref">`, ty vynecháme). Přesměrování mají `class="mw-redirect"`
(např. *Husité* → *Husitství*), cílový titul dá Action API s `redirects=1`.

Prototyp parseru nad HTML byl ověřen na 7 problematických dnech (fixtures) – **všechny
události měly rok** a počty Česko/Svět odpovídají:

| Den | Česko | Svět | Zvláštnost |
|---|---|---|---|
| 28. 10. | 15 | 16 | podbody (1918, 1945), reference |
| 29. 2. | 3 | 3 | nejchudší den |
| 8. 12. | 1 | 12 | jediná česká událost |
| 15. 8. | 10 | 12 | roky bez odkazu |
| 22. 5. | 4 | 70 | komentář v nadpisu, dělení podle století |
| 31. 12. | 10 | 14 | podbody |
| 7. 2. | 8 | 18 | `{{0}}`, `[[1984 v dopravě\|1984]]` |

**Párování se feedem:** text položky z HTML (po normalizaci mezer a `&nbsp;`) se **přesně shoduje**
s `text` ve feedu – 31/31 pro 28. 10., 6/6 pro 29. 2. Feed tedy použijeme na revize a úvody
článků a stránku dne na rozdělení Česko/Svět. Pozor: feed podle svého regexu vyžaduje pomlčku
za rokem, řádky bez ní (6 za rok) ve feedu chybí – ty dostanou revize přes Action API.

**Hlavní článek události** – řádek odkazuje v průměru na 2,7 článku a nejčastější odkazy jsou
obecné: *Praha* (395 událostí za rok), *Spojené státy americké* (257), *Francie* (191),
*Druhá světová válka* (122), *Papež* (100). 93 článků je odkazováno z ≥ 20 událostí. Jejich
zobrazení by přebila vše ostatní, proto je jako hlavní článek nesmíme brát (návrh: vynechat
roky a „rozcestníkové“ články s vysokou frekvencí odkazů v rámci roku; ověřit v kroku 3).

## 3. Revize

- **Aktuální revize hromadně:** `action=query&prop=info&titles=A|B|…` → `lastrevid`
  (50 titulů na dotaz, s `redirects=1` vyřeší i přesměrování). Ověřeno:
  `Pochod na Řím → lastrevid 25411248`.
- **Bez dalšího dotazu:** feed `onthisday` má `revision` u každé odkazované stránky (viz výše).
- **Revize stránky dne** (zdroj textu události!): hlavička `content-revision-id` u Parsoid HTML.
- **Odkaz na revizi:** `https://cs.wikipedia.org/w/index.php?title={titul}&oldid={revize}`
- **Odkaz na historii (autoři):** `https://cs.wikipedia.org/w/index.php?title={titul}&action=history`
  (stejný tvar vrací feed i page/summary v `content_urls.desktop.revisions`).

Důležité: **text události pochází ze stránky dne**, ne z odkazovaného článku. Atribuce karty
proto musí uvádět stránku dne (a její revizi); článek je odkaz „více“ a zdroj úvodu.

## 4. Signály

Ověřeno na 5 velkých a 5 drobných událostech z 28. 10. Hlavní článek jsem pro test vybral ručně.
`pv90` = REST Pageviews 30. 7.–27. 10. (součet), `dní` = počet vrácených dní z 90,
`langl` = cs `prop=langlinks`, `wd` = Wikidata sitelinks typu *wiki*, `délka` = bajty,
`cs/en sel` = článek je v `selected` daného jazyka (spárováno přes Wikidata ID).

| Rok | Hlavní článek | Druh | pv90 | dní | langl | wd | délka | cs sel | en sel |
|---|---|---|---|---|---|---|---|---|---|
| 1918 | Vznik Československa | velká | 6 052 | 90 | **7** | 8 | 46 639 | **ne** | ne |
| 1886 | Socha Svobody | velká | 6 045 | 90 | 148 | 151 | 121 033 | ano | ano |
| 1636 | Harvardova univerzita | velká | 3 086 | 90 | 136 | 137 | 25 593 | ano | ne |
| 1922 | Pochod na Řím | velká | 662 | 90 | 46 | 47 | 5 001 | ne | ne |
| 312 | Bitva u Milvijského mostu | velká | 549 | 90 | 43 | 44 | 6 314 | ne | ano |
| 1935 | Švehlův most | drobná | 151 | 84 | 1 | 2 | 2 805 | ne | ne |
| 1928 | Fakultní Thomayerova nemocnice | drobná | **1 158** | 90 | 1 | 2 | **11 023** | ne | ne |
| 2020 | Chrám sv. archanděla Michaela (Petřín) | drobná | **1 163** | 90 | 4 | 5 | 11 038 | ne | ne |
| 1914 | Bitva u Penangu | drobná | 65 | 74 | 15 | 16 | 4 158 | ne | ne |
| 1969 | Bohumil Peroutka | drobná | 192 | 84 | 0 | 1 | 4 506 | ne | ne |

**Kde signály selhávají:**
- **Zobrazení** měří oblibu *článku*, ne významnost *události*: nemocnice (1 158) a kostel
  (1 163) mají víc zobrazení než Pochod na Řím (662). Obecné články (Praha) by vyhrály vždy.
  Silná sezónnost: *Vznik Československa* měsíčně 58 (červenec, jen 3 dny) → 697 → 1 680 → 3 635
  (říjen). REST API **některé dny nevrací** (Švehlův most 84 z 90, Bitva u Penangu 74) – nelze
  předpokládat jednu položku na den.
- **Jazykové verze** systematicky **znevýhodňují české události**: *Vznik Československa* má 7
  verzí, *Socha Svobody* 148. Peroutka 0. Bez normalizace by česká pětice prohrála se světem.
  Wikidata sitelinks = langlinks + 1 (sama cs) – stejný signál, levnější dotaz.
- **Délka** odráží zralost článku, ne událost: nemocnice 11 kB > Pochod na Řím 5 kB;
  Socha Svobody 121 kB je odlehlá hodnota.
- **Selected** je řídké (cs 5 položek na den, en 17) a redakční: cs výběr pro 28. 10. **neobsahuje
  vznik Československa** (obsahuje Dornier Do 19, Gulliverovy cesty…). Dobrý bonus, špatné kritérium.

**Závěr pro výběr:** signály normalizovat **v rámci dne a sekce** (percentily, logaritmus
zobrazení), Česko a Svět hodnotit odděleně, „selected“ jen jako bonus. Potvrzuje to zadání, že
žádný signál nesmí rozhodovat sám.

**Dávkování a počty dotazů** (naměřeno):

| Data | Volání | Dávka |
|---|---|---|
| stránka dne (Česko/Svět, revize) | REST `page/html` | 1 na den |
| revize + úvody + Wikidata ID článků | feed `events` | 1 na den |
| selected | feed `selected` | 1 na den |
| délka, `lastrevid`, přesměrování | Action `prop=info\|pageprops` | 50 titulů / dotaz |
| jazykové verze | Wikidata `wbgetentities` `props=sitelinks` | 50 položek / dotaz (cs `langlinks`: 8 dotazů na 50 titulů kvůli stránkování) |
| zobrazení 60 dní | Action `prop=pageviews` | nepravidelně 2–20 titulů / dotaz (50 titulů = 5 dotazů, `pvipcontinue`) |
| zobrazení 90 dní | REST Pageviews | 1 titul / dotaz |

**Odhad pro import jednoho dne** (medián ~22 událostí, hlavní článek ≈ 1 na událost):
3 (stránka, events, selected) + 1 (info) + 1 (Wikidata) + ~3 (pageviews 60 d) ≈ **8–10 dotazů**.

**Odhad pro celý rok:** 366 × ~9 ≈ **3 300 dotazů** (s 90denními zobrazeními přes REST
+ ~9 400 dotazů ≈ 12 700). Za rok je odkazováno ~11 900 unikátních článků, ale signály potřebujeme
jen pro hlavní články.

| Limit | Celý rok (3 300 dotazů) |
|---|---|
| bez kontaktu v UA: 10 / min | ≈ 5,5 hodiny |
| se správným UA: 200 / min | ≈ 17 minut |

Denní cron pak stahuje jen aktuální den: ~10 dotazů.

## 5. Úvod článku (page/summary)

`GET https://cs.wikipedia.org/api/rest_v1/page/summary/Vznik_%C4%8Ceskoslovenska`:

```json
{
  "type": "standard",
  "title": "Vznik Československa",
  "wikibase_item": "Q3565888",
  "revision": "26271507",
  "timestamp": "2026-10-04T22:16:34Z",
  "extract": "Vznik Československa byl proces, který vedl k ustavení samostatného československého státu. Československo bylo vyhlášeno 28. října 1918. …",
  "content_urls": { "desktop": { "page": "…/wiki/Vznik_%C4%8Ceskoslovenska",
                                 "revisions": "…/wiki/Vznik_%C4%8Ceskoslovenska?action=history" } }
}
```

- `extract` je prostý text úvodu (zde 260 znaků), `extract_html` jeho HTML.
- Přesměrování vyřeší sám (`Husité` → `title: "Husitství"`).
- `cache-control: s-maxage=1209600, max-age=300`, revize i v `etag`.
- **Stejná pole jsou už ve feedu `onthisday`** u každého odkazovaného článku → samostatné volání
  page/summary je potřeba jen pro články mimo feed.

## 6. Pravidla Wikimedia

### User-Agent
[User-Agent policy](https://foundation.wikimedia.org/wiki/Policy:Wikimedia_Foundation_User-Agent_Policy):
formát `<název>/<verze> (<kontakt>) <knihovna>/<verze>`, kontakt = e-mail, web nebo uživatel wiki;
chybějící nebo obecný UA může skončit `403`. Prohlížečový UA se považuje za škodlivý.

**Návrh:** `TentoDen/1.0 (https://github.com/<účet>/tento-den; <kontaktní e-mail>) PHP/8.3`
– kontakt bude v `.env` (`WIKIMEDIA_CONTACT`) a validace konfigurace odmítne start, když chybí.

### Limity
[Wikimedia APIs/Rate limits](https://www.mediawiki.org/wiki/Wikimedia_APIs/Rate_limits):

| Klient | Limit |
|---|---|
| neidentifikovaný (bez kontaktu v UA) | **10 požadavků / min** |
| bot se správným UA, bez přihlášení | **200 / min** |
| přihlášený, zavedený editor | 2 000 / min |

- Souběžně nejvýš **3** požadavky; při `429`/`503` respektovat `Retry-After`, bez něj čekat
  ≥ 5 s nebo exponenciálně.
- **Ověřeno v praxi:** během průzkumu (UA bez kontaktu) přišlo po několika desítkách dotazů během pár minut
  `HTTP 429`, `retry-after: 37`, `x-envoy-ratelimited: true` (fixture `rate-limit-429.txt`).
- [API:Etiquette](https://www.mediawiki.org/wiki/API:Etiquette): dotazy sériově, více titulů
  přes `|`, u neinteraktivních úloh `maxlag` (příklad `maxlag=1`), `Accept-Encoding: gzip`,
  cachovat.

### Licence a atribuce
Texty Wikipedie: **CC BY-SA 4.0** (a GFDL)
([Terms of Use, část 7](https://foundation.wikimedia.org/wiki/Policy:Terms_of_Use)).

Atribuce podle [CC BY-SA 4.0, oddíl 3(a)](https://creativecommons.org/licenses/by-sa/4.0/legalcode.cs)
a [Reusing Wikipedia content](https://en.wikipedia.org/wiki/Wikipedia:Reusing_Wikipedia_content).
U každé karty musí být:
1. **autoři** – u Wikipedie stačí odkaz na stránku nebo na historii (Terms of Use povolují
   odkaz/URL místo výčtu autorů),
2. **název díla** a **odkaz** na něj (u nás na konkrétní revizi `oldid`),
3. **licence** s odkazem na její text,
4. **označení úprav**, pokud text měníme (3(a)(1)(B)),
5. (oznámení o autorských právech a vyloučení záruk – stačí „přiměřeným způsobem“ dle 3(a)(2),
   např. v patičce / stránce o projektu).

Návrh textu karty: *Zdroj: Wikipedie, „28. říjen“ (revize 26193496, [autoři](…history)),
upraveno, [CC BY-SA 4.0](…)*.

### Platí share-alike i pro upravený nebo zkrácený text?
**Ano, pokud jde o adaptaci.** Licence (oddíl 1(a)) definuje *Adapted Material* jako materiál
„derived from or based upon the Licensed Material and in which the Licensed Material is translated,
altered, arranged, transformed, or otherwise modified **in a manner requiring permission**“ a podle
oddílu 3(b) musí být adaptace šířena pod licencí CC se stejnými prvky (BY-SA). Terms of Use
výslovně: úpravy a doplňky textu musí být pod CC BY-SA 4.0 nebo novější a úpravy musí být označené.
Čistě technické úpravy (odstranění referencí, sjednocení mezer) adaptací být nemusí, zkrácení
nebo přeformulování ano – bezpečné je vždy uvádět licenci a „upraveno“.

Pro projekt z toho plyne:
- zkrácený/upravený text události i úvodu = CC BY-SA 4.0 + označení „upraveno“,
- **budoucí AI komentář**, který text převypráví, je s vysokou pravděpodobností také adaptace →
  šířit pod CC BY-SA 4.0,
- zdrojový kód aplikace adaptací textu není – může mít vlastní licenci (fixtures v repozitáři
  jsou označené zvlášť),
- **obrázky** mají každý vlastní licenci → podle zadání žádné nepoužíváme (feed je vrací
  v `thumbnail`/`originalimage`, ignorujeme je).

Neověřeno ze zdrojů: zda jsou samotná fakta (rok + událost) chráněná – stránka Reusing to
neřeší, proto se na to nespoléháme a atribuujeme vždy.

## 7. Omezení a rizika

| Riziko | Dopad | Opatření |
|---|---|---|
| nejednotný formát stránek dnů | špatný rok/sekce | parsovat Parsoid HTML (šablony vyřešené), testy na 7 fixtures; neznámé řádky logovat a přeskočit |
| dny s málo daty (Česko 1 událost 8. 12. a 26. 12., 8 dní < 3; Svět 3 události 29. 2.) | méně než 2 české karty | pravidlo „aspoň 2 z Česka, pokud existují“ – doplnit světovými; filtr nudy na chudých dnech zmírnit |
| 29. 2. jen v přestupném roce | – | importovat vždy 366 dní; mimo přestupný rok se 29. 2. nezobrazí jako „dnes“ (rozhodnutí v kroku 3) |
| výpadek / 429 API | import selže | import je dávkový a opakovatelný; aplikace čte jen z DB, nikdy z Wikipedie za běhu; Retry-After + backoff |
| obecné články (Praha, USA) jako hlavní článek | zkreslené signály | vyloučit roky a „rozcestníky“ podle frekvence odkazů |
| sezónnost zobrazení | výročí dne mají špičku | porovnávat jen v rámci stejného dne a stejného okna |
| změna stránky dne (editace) | jiný text, nové/smazané události | ukládat revizi a hash textu; při reimportu porovnat |
| vandalismus | nesmysl v kartě | ukládáme `oldid` → odkaz vede na konkrétní verzi; ruční vyřazení konzolovým příkazem |
| PageViewInfo jen 60 dní | zadání chtělo 90 | pro relativní pořadí v rámci dne stačí; 90 dní by stálo ~9 400 dotazů navíc za rok |

## 8. Návrh datového modelu

Tři tabulky místo jedné: článek (Praha) sdílí mnoho událostí a jeho signály se obnovují nezávisle.

```sql
CREATE TABLE events (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    month               TINYINT UNSIGNED NOT NULL,          -- 1–12
    day                 TINYINT UNSIGNED NOT NULL,          -- 1–31
    year                SMALLINT NOT NULL,                  -- záporné = př. n. l.
    region              ENUM('cz', 'world') NOT NULL,
    text                TEXT NOT NULL,                      -- prostý text ze stránky dne
    text_hash           CHAR(64) NOT NULL,                  -- SHA-256 normalizovaného textu
    source_title        VARCHAR(255) NOT NULL,              -- „28. říjen“
    source_revision_id  INT UNSIGNED NOT NULL,              -- oldid stránky dne
    is_selected         BOOLEAN NOT NULL DEFAULT FALSE,     -- je ve Vybraných výročích
    boring_reason       VARCHAR(64) NULL,                   -- důvod vyřazení filtrem nudy
    score               DECIMAL(6,4) NULL,                  -- výsledek výběru
    fetched_at          DATETIME NOT NULL,                  -- UTC
    last_seen_at        DATETIME NOT NULL,                  -- při reimportu; zmizelé události
    UNIQUE KEY uq_event (month, day, region, year, text_hash),
    KEY idx_day (month, day, region)
);

CREATE TABLE articles (
    page_id             INT UNSIGNED PRIMARY KEY,
    title               VARCHAR(255) NOT NULL UNIQUE,
    revision_id         INT UNSIGNED NOT NULL,              -- oldid, na který vede odkaz
    wikidata_id         VARCHAR(16) NULL,
    length              INT UNSIGNED NULL,
    language_count      SMALLINT UNSIGNED NULL,
    pageviews_60d       INT UNSIGNED NULL,
    extract             TEXT NULL,                          -- úvod (pro budoucí kontext/AI)
    fetched_at          DATETIME NOT NULL
);

CREATE TABLE event_articles (
    event_id            INT UNSIGNED NOT NULL,
    page_id             INT UNSIGNED NOT NULL,
    position            TINYINT UNSIGNED NOT NULL,          -- pořadí odkazu v textu
    is_primary          BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (event_id, page_id),
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES articles (page_id)
);
```

Výsledná pětice dne (`day_selections`: month, day, position, event_id) přijde s algoritmem
výběru v dalším kroku.

## 9. Výsledek prvního importu celého roku (29. 10. 2026)

`make import-year` (krok 3): **366 / 366 dní ok**, exit code 0, žádný nový typ chyby.
Výslovná pravidla (`config/day-page-rules.neon`) se uplatnila jen u 4 vytipovaných dnů
(6. 1., 17. 4., 30. 6., 14. 12.).

| | Česko | Svět |
|---|---|---|
| aktivní události | 3 071 | 6 274 |
| medián na den | 7 | 15 |
| minimum na den | 1 (8. 12., 26. 12.) | 3 (29. 2.) |
| bez hlavního článku | 93 | 278 |

- Dny s méně než 2 českými událostmi: **8. 12. a 26. 12.** (po jedné) – pravidlo „aspoň 2
  z Česka, pokud existují“ tam doplní světové.
- Bez hlavního článku je 371 událostí; 267 z nich neodkazuje na žádný existující článek,
  zbylých 104 jen na obecné články.
- `event_articles` drží všechny odkazy: kontrola 30 dní (5 vytipovaných + 25 náhodných) proti
  HTML stejné revize a čerstvě vyřešeným odkazům – 844 událostí, 0 chybějících, 0 přebývajících,
  0 v jiném pořadí. Přepočet hlavního článku jen z DB (`event_articles` + seznam obecných)
  souhlasí s uloženým `is_primary` u všech 9 345 událostí – změna pravidla nepotřebuje nové
  stahování.
- Nejčastěji odkazované články mimo seznam obecných (kandidáti, nic není změněno):
  Premiéra (48), Rudá armáda (48), Čína (48), Karel IV. (47), Napoleon Bonaparte (47),
  Škoda Auto (46), Moskva (44), Morava (43), Portugalsko (42), Sociální demokracie (Česko) (41),
  Švédsko (41), Adolf Hitler (40), Byzantská říše (39), T. G. Masaryk (39), Edvard Beneš (38).
