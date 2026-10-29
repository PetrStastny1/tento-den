# Otázky k pohovoru

Otázky, které můžou k tomuto kódu padnout, s krátkou odpovědí a odkazem na místo v kódu.

### 1. Jak je backend rozvrstvený a proč?
Presenter (controller) → request DTO → služba → repository → response DTO. Presenter řeší jen
HTTP, služba logiku, SQL je výhradně v repository. Stejné služby používá konzole (import, export),
takže logika není svázaná s HTTP.
→ [DayPresenter](../backend/app/Api/Day/DayPresenter.php), [DayService](../backend/app/Model/Selection/DayService.php),
[CardRepository](../backend/app/Model/Selection/CardRepository.php), [CardResponse](../backend/app/Api/Dto/CardResponse.php)

### 2. Jak funguje Nette DI a proč služby registrujete ručně?
Kontejner se kompiluje z `.neon`; závislosti jdou jen konstruktorem a autowiringem podle typu.
Služby jsou vyjmenované explicitně (je vidět, co v kontejneru je) a zdroje dat jsou registrované
pod rozhraním (`DayPageSource`), takže testy dosadí falešnou implementaci.
→ [services.neon](../backend/config/services.neon), [Bootstrap](../backend/app/Bootstrap.php)

### 3. Proč presentery implementují `IPresenter` a ne `UI\Presenter`?
`UI\Presenter` je pro HTML (šablony, komponenty, signály, session). JSON API potřebuje jen
`run(Request): Response` – celý tok je v jedné metodě, bez životního cyklu a `inject*` metod,
a v testu stačí zavolat `run()`.
→ [docs/rozhodnuti.md](rozhodnuti.md), [EventPresenter](../backend/app/Api/Event/EventPresenter.php)

### 4. Jak se spravuje schéma databáze?
Jen SQL migracemi (Nextras Migrations), `make migrate`. Web při každém startu zkontroluje čekající
migrace: produkce odpoví 503, development varuje. Konzole kontrolu nedělá, aby šlo migrovat i
prázdnou databázi.
→ [Migrations](../backend/app/Core/Database/Migrations.php), [PendingMigrationsCheck](../backend/app/Core/Database/PendingMigrationsCheck.php),
[migrations/structures](../backend/migrations/structures/)

### 5. Wikipedie nemá ID událostí. Jak poznáte, že upravená věta je stále tatáž událost?
Párování v rámci dne, sekce a roku: přesná shoda normalizovaného textu, pak vzájemně nejlepší
dvojice 1:1 s podobností ≥ 0,70 (≥ 0,60 se stejným hlavním článkem) a odstupem od druhého
kandidáta. Co nejde rozhodnout, jde k ruční revizi – nic se neodhaduje ani nemaže. Prahy jsou
nastavené podle skutečných editací z historie stránek, včetně vandalismu.
→ [EventMatcher](../backend/app/Model/Events/EventMatcher.php), tabulka v [rozhodnuti.md](rozhodnuti.md)

### 6. Co se stane, když Wikipedie vypadne nebo změní formát stránky?
Import dne je atomický: vše, co může selhat (síť, parser, podezřelý úbytek), proběhne před
transakcí; data a stav „ok“ se zapíšou najednou. Chyba zapíše jen stav `error`, uložená data
zůstanou. Parser, který něčemu nerozumí, shodí celý den – jinak by se uložené události mylně
označily jako zmizelé.
→ [DayImportService](../backend/app/Model/Import/DayImportService.php), [DayPageParser](../backend/app/Model/Events/DayPageParser.php)

### 7. Jak se chováte k API Wikimedia?
User-Agent s kontaktem z `.env`, sériově nejvýš 200 požadavků/min, při 429/503 čekání podle
`Retry-After`, `maxlag` se kontroluje i v JSON těle odpovědi s HTTP 200. Hromadné dotazy po 50.
→ [PoliteHttpClient](../backend/app/Core/Http/PoliteHttpClient.php), [ActionApiClient](../backend/app/Model/Wikipedia/ActionApiClient.php)

### 8. Jak vybíráte karty dne?
Signál = počet jazykových verzí článku z Wikidat, logaritmicky normalizovaný zvlášť pro Česko a
Svět. Pak deterministický výběr 10 karet: aspoň 3 české, nudné až nakonec, jinak podle skóre;
století rozhoduje jen při shodném skóre. Události připomínané státním svátkem nebo významným
dnem (zákon č. 245/2000 Sb.) jsou vždy v desítce a první. Zobrazují se od nejnovější po nejstarší. Známé omezení:
hlavní článek bývá kulisa, proto zlatá sada vychází slabě (4 z 8) – měřím to reportem.
→ [EventScorer](../backend/app/Model/Selection/EventScorer.php), [DaySelector](../backend/app/Model/Selection/DaySelector.php),
[kvalita.md](kvalita.md)

### 9. Jak je zabezpečené API?
Jen GET (jinak 405), validace vstupu v request DTO (400 se srozumitelnou zprávou), jednotný JSON
formát chyb bez interních detailů, bezpečnostní hlavičky (CSP `default-src 'none'`, `DENY`,
`no-referrer`, `nosniff`), žádné cookies, nginx pouští do PHP jen front controller.
→ [ApiException](../backend/app/Api/ApiException.php), [ErrorPresenter](../backend/app/Api/Error/ErrorPresenter.php),
[common.neon](../backend/config/common.neon), [nginx](../docker/nginx/default.conf)

### 10. Jak testujete?
Nette Tester nad testovací databází, offline: fixtures jsou skutečné odpovědi Wikipedie včetně
historických revizí. Testy pokrývají parser, párování, idempotenci, výpadky, 429/`maxlag`,
migrace na prázdné DB, validaci konfigurace a API; test hygieny hlídá tajemství v gitu. Frontend:
Vitest + React Testing Library.
→ [tests/](../backend/tests/), [App.test.jsx](../frontend/src/App.test.jsx)

### 11. Jak řešíte tajemství a konfiguraci?
Jen `.env` (v `.gitignore`), `make init-env` vygeneruje náhodná hesla, konfigurace se validuje
přes Nette Schema před sestavením kontejneru. PHP kontejner nedostane root heslo databáze.
Test hygieny kontroluje, že v gitu není `.env` ani výchozí hesla.
→ [EnvironmentConfigLoader](../backend/app/Core/Config/EnvironmentConfigLoader.php), [init-env.php](../bin/init-env.php),
[RepositoryHygieneTest](../backend/tests/Hygiene/RepositoryHygieneTest.phpt)

### 12. Jak je postavený frontend a jak funguje demo bez backendu?
React + React Router, jedna datová vrstva se dvěma režimy (živé API / statické JSON exportované
příkazem `export:static`). Texty jen jako text, odkazy jen na Wikipedii, oblíbené v
`localStorage` s fallbackem do paměti. GitHub Actions postaví statickou verzi a nasadí ji na Pages.
→ [days.js](../frontend/src/data/days.js), [favorites.js](../frontend/src/lib/favorites.js),
[EventCard](../frontend/src/components/EventCard.jsx), [pages.yml](../.github/workflows/pages.yml)

### 13. Proč je vzhled tak jednoduchý?
Zkoušel jsem bubliny nad vektorovým glóbem a AI titulky s automatickou kontrolou faktů. Fungovalo
to, ale přidávalo závislosti (d3-geo, mapová data), zpomalilo první vykreslení a AI titulky
by byly další vrstva, kterou je třeba hlídat. Pro portfolio má přednost čitelný, ověřitelný
obsah: deset stejných karet s celým textem ze zdroje.
→ [EventCard](../frontend/src/components/EventCard.jsx), [rozhodnuti.md](rozhodnuti.md)

### 15. Jak jste řešili jmeniny, když se český a slovenský kalendář liší?
Každá země má svůj zdroj: CZ ze sekce „Svátky“ cs.wikipedia (stejná revize jako události), SK
z úvodu stránek dnů sk.wikipedia. Slovenský řádek z cs.wikipedia se jen porovnává a rozdíly jdou
do reportu – nic se neslučuje. Význam jména se bere jen z článku, jehož položka Wikidat je
křestní jméno; známý nositel z Wikidat podle státní příslušnosti a počtu jazykových verzí.
→ [NameDayParser](../backend/app/Model/NameDays/NameDayParser.php), [SlovakNameDayParser](../backend/app/Model/NameDays/SlovakNameDayParser.php),
[ActionApiNameArticleSource](../backend/app/Model/Wikipedia/ActionApiNameArticleSource.php), [jmeniny.md](jmeniny.md)
