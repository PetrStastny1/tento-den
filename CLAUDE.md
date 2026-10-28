# Tento den / This Day

Ukázkový projekt do portfolia (pozice Full Stack Developer – React.js + PHP/Nette).
**Kvalita kódu a architektura mají přednost před množstvím funkcí.** Stavíme po verzích.

## Cílová podoba aplikace

Každý den 5 karet se zajímavými událostmi (výsledný mix po všech verzích):

| Počet | Typ karty |
|---|---|
| 2× | historická událost ze světa, která se stala v tento den |
| 1× | historická událost z Česka (Československa) v tento den |
| 1× | aktuální událost z posledních dnů (zprávy) |
| 1× | kuriozita „Víte, že…“ – ověřená zajímavost, která pobaví |

- Vybírá zajímavé a podstatné věci, ne nudné drobnosti (sportovní výsledky, otevření obchodu apod.).
- Veřejná, bez přihlášení. Oblíbené karty a nastavení jen v prohlížeči (`localStorage`).
- Import dat a generování karet běží jen na serveru (konzolové příkazy, cron) – **žádné admin
  rozhraní na webu**.
- „Dnes“ je pro všechny stejné podle časové zóny **Europe/Prague**.

## Verze 1 (aktuální)

- **Jen historické karty:** 5 výročí na den (svět + Česko, **aspoň 2 z Česka, pokud existují**).
- Automatický import a výběr z Wikipedie, REST API, React frontend.
- Prohlížení výročí podle **dne + měsíce** (ne archiv konkrétních dat).
- Oblíbené v `localStorage`, bez přihlášení.
- **Jen čeština.**

### Výběr událostí
- Kombinace signálů: zobrazení článku, počet jazykových verzí, délka článku, výběr redaktorů
  („selected“). **Žádný signál sám o sobě nerozhoduje.**
- K tomu filtry nudy (sport, otevření obchodu apod.) a rozmanitost pětice.
- **AI výběr nedělá.**

## Později (po verzi 1)

- **AI podání** (Claude API, jen na serveru, klíč jen v `.env`). Tón vtipně edukační – suchý,
  inteligentní humor s nadhledem, bez vulgarit. **U tragédií (války, katastrofy, oběti) žádné
  vtipy** – jen věcně a s respektem. Aplikace musí fungovat i bez AI (pak ukáže fakt bez komentáře).
  Karta má oddělené části:
  - **fakt** – převzatý ze zdroje (Wikipedie), s rokem a odkazem,
  - **komentář** – vtipné podání (smí ho napsat AI, ale nesmí přidat žádný nový fakt),
  - **„proč to stojí za to vědět“** – krátký poučný kontext, opět jen ze zdroje.
- **Aktuality:** cs Portál:Aktuality, en Portal:Current events. S nimi archiv konkrétních dat.
- **Kuriozity:** archiv „Víte, že…“ (cs) a „Did you know…“ (en).
- **Angličtina** („This Day“): přepínač CZ / EN, výchozí jazyk podle prohlížeče, volba
  v `localStorage`. Přeložené rozhraní i obsah; anglická fakta přednostně z anglické Wikipedie
  (přes propojení článků), ne z volného překladu.
- **PWA** (hlavně iPhone přes Safari).
- **Nasazení:** levný VPS s Dockerem a Caddy.

## Zásady dat

- **Wikipedie není záruka pravdivosti, jen dohledatelnosti.** Ke každé události ukládáme ID revize
  článku (`oldid`) a čas načtení; odkaz vede na tuto revizi.
- **Licence CC BY-SA:** u každé karty název článku, odkaz, licence a odkaz na historii článku
  (autoři). **Žádné obrázky.**
- Fakta se **nikdy nevymýšlí** – jen to, co je ve zdroji.

## Stack (neměnit bez souhlasu uživatele)

- **Backend:** PHP 8.3, Nette 3.2 (Application, DI, Database Explorer, Tester), MariaDB,
  migrace přes Nextras Migrations.
- **Architektura backendu:** Controller (Nette presenter jako API endpoint) → Service → Repository.
  DTO pro vstupy i výstupy. Závislosti výhradně přes Dependency Injection (konstruktor) – žádné
  statické volání služeb ani globální stav.
- **Frontend:** čistý React (Vite, JavaScript/JSX, bez Next.js), Tailwind CSS, React Router.
- Backend a frontend jsou **striktně oddělené** a komunikují jen přes REST API (JSON).
- **Docker Compose** pro lokální běh (nginx, php-fpm, MariaDB, Node pro frontend).

## Pravidla práce

1. **Než začneš psát kód, vždy stručně popiš plán a počkej na „ok“.**
2. Každé architektonické rozhodnutí krátce zdůvodni (1–2 věty) a zapiš do
   [docs/rozhodnuti.md](docs/rozhodnuti.md).
3. Nepřidávej knihovny, které nejsou nutné. Každou novou závislost zdůvodni.
4. **Žádná tajemství v repozitáři** – jen `.env` (v `.gitignore`) a `.env.example` s ukázkovými
   hodnotami.
5. Commituj až po úspěšném projití kontrol, které uživatel u každého kroku definuje.
6. Když si nejsi jistý chováním externího API nebo knihovny, ověř si ho (dokumentace, testovací
   volání) a nehádej.
7. **Jazyk:** kód a komentáře v kódu anglicky; dokumentace a commit messages česky (stručně,
   v rozkazovacím způsobu, např. „Přidej import událostí z Wikipedie“).

## Osvědčené postupy převzaté z ~/VSCode/app-starter

Jiný stack (NestJS/Angular) – **kód nepřebírat**, jen principy. Zavádí se až v kroku, kde jsou potřeba:

- **Test hygieny repozitáře** – test (Nette Tester) nad `git ls-files --cached --others
  --exclude-standard`: v gitu nesmí být žádný `.env*` kromě `.env.example`, žádná výchozí hesla
  ani klíče.
- **Skript na vygenerování `.env`** – vytvoří `.env` z `.env.example`, prázdná tajemství vyplní
  náhodnými hodnotami, nic nevypisuje, existující `.env` nepřepíše, práva `0600`.
- **Schéma databáze jen přes migrace** (Nextras Migrations) – žádné ruční úpravy schématu;
  v produkci odmítnout start při neprovedených migracích.
- **Validace konfigurace při startu** – chybějící nebo neplatná proměnná prostředí = aplikace
  nenastartuje se srozumitelnou chybou (povinné `APP_ENV`, žádné tiché přepnutí do vývojového režimu).
- **Struktura dokumentace** – `README.md` (spuštění, proměnné prostředí), `SECURITY.md`
  (bezpečnostní opatření, checklist pro produkci), `CHANGELOG.md`, `docs/rozhodnuti.md`.
