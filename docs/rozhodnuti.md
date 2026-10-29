# Architektonická rozhodnutí

Krátký záznam rozhodnutí a jejich důvodů. Nejnovější dole.

## Krok 1 – kostra projektu (2026-10-28)

### Monorepo s oddělenými `backend/` a `frontend/`
Backend a frontend mají vlastní závislosti i nástroje a komunikují jen přes REST API. Jeden
repozitář drží dohromady kód, Docker konfiguraci a dokumentaci, aby šel projekt spustit jedním
příkazem.

### nginx jako jediný vstupní bod (stejný origin pro web i API)
Prohlížeč mluví jen s nginx: `/api/*` jde do PHP, ostatní na frontend. Díky stejnému originu
nepotřebujeme CORS a stejná topologie bude i v produkci (tam nginx/Caddy servíruje build ze `dist/`).

### nginx posílá do PHP jen front controller
Pro `/api/` je `SCRIPT_FILENAME` napevno `www/index.php` a nginx nemá adresář backendu vůbec
připojený, takže žádný jiný PHP soubor ani konfigurace nejsou z webu dosažitelné.
`SCRIPT_NAME` je `/index.php`, protože z něj Nette počítá base path – s výchozí hodnotou
z `fastcgi_params` (= URL) by router nic nenašel.

### Jednotlivé balíčky Nette místo šablony `nette/web-project`
Šablona přináší Latte, Forms, Security, Assets a další balíčky, které JSON API nepotřebuje.
Instalujeme jen `application`, `bootstrap`, `di`, `database`, `http` a `tracy`.
`nette/application` a `nette/bootstrap` jsou zamčené na `~3.2.0`, protože Composer by jinak
vybral už řadu 3.3 a stack je Nette 3.2.

### Presentery implementují `IPresenter`, ne `UI\Presenter`
`Nette\Application\UI\Presenter` je postavený pro HTML stránky: šablony (Latte), komponenty,
formuláře, signály (`?do=`), flash zprávy, persistentní parametry, kanonizaci URL a session.
Pro JSON API je to všechno mrtvá váha a navíc skryté chování (např. automatická přesměrování
nebo `$this->sendJson()` přes výjimku `AbortException`).

`IPresenter` je naopak rozhraní s jedinou metodou `run(Request): Response` – presenter je
obyčejná třída, která dostane požadavek a vrátí odpověď. Výhody:
- **čitelnost:** celý tok je vidět v jedné metodě, žádné životní cykly (`startup`, `action*`,
  `render*`, `beforeRender`),
- **čisté DI:** závislosti jen konstruktorem; `UI\Presenter` k tomu tlačí `inject*` metody
  a `@inject` property,
- **testovatelnost:** v testu stačí zavolat `run()` a zkontrolovat vrácený `JsonResponse`,
- **méně závislostí:** nepotřebujeme Latte.

Routing, mapování presenterů, error presenter i DI kontejner Nette Application přitom
zůstávají – nevzdáváme se frameworku, jen jeho HTML vrstvy.

### Vrstvy `app/Api` (controllery) a `app/Model` (služby, repozitáře, DTO)
HTTP vrstva (presentery) je oddělená od aplikační logiky, takže služby jdou použít i z konzolových
příkazů (import, cron). I health check prochází celou cestou presenter → service → repository
→ DTO, aby měly další moduly vzor.

### Služby registrované v `services.neon` explicitně
Automatické hledání (`search`) by vyžadovalo další balíček (RobotLoader). Explicitní seznam je
navíc na první pohled přehledný – je vidět, co v kontejneru je.

### Chyba databáze se loguje, ale neposílá klientovi
`HealthService` zachytí výjimku databáze, zaloguje ji (Tracy do `log/`) a vrátí jen
`{"status":"error"}` s kódem 503. Detaily (hostname, uživatel) by prozradily vnitřní
infrastrukturu.

### JSON error presenter a `catchExceptions: true` i ve vývoji
API musí vracet JSON vždy, i při 404/405/500 – jinak by se frontend musel bránit HTML odpovědím.
Neočekávané výjimky se logují, klient dostane jen kód a obecnou zprávu.

### Proměnné prostředí z Docker Compose, předané jako dynamické parametry
Compose předá PHP kontejneru jen proměnné, které aplikace potřebuje (ne root heslo databáze);
nepotřebujeme proto knihovnu na čtení `.env` (phpdotenv). `Bootstrap` je dostane v konstruktoru
a předá je přes `addDynamicParameters()`: vyhodnocují se až za běhu, takže se hesla nikdy
nezapíšou do zkompilovaného kontejneru v `temp/`, a testy můžou nabootovat s vlastními hodnotami.

### Bezstavové API bez cookies a bez prozrazování verzí
`disableNetteCookie` vypíná cookie `_nss`, `X-Powered-By` je vypnutá v Nette i v PHP
(`expose_php = Off`). API nemá session ani přihlášení, takže cookies nepotřebuje.

### `make up` odmítne start bez hesel
`scripts/check-env.sh` zkontroluje, že `.env` existuje a hesla k DB nejsou prázdná, a vypíše
srozumitelnou chybu. Compose má navíc `${VAR:?}` jako druhou pojistku. Prázdná hesla
v `.env.example` místo ukázkových zabrání tomu, aby se slabé výchozí heslo dostalo do produkce.

### MariaDB 11.4 (LTS), port jen na 127.0.0.1:3307
LTS verze s dlouhou podporou. Port 3306 často obsadí jiné lokální databáze; navázání na
127.0.0.1 zabrání přístupu z místní sítě.

### `node_modules` v pojmenovaném Docker volume
npm v kontejneru instaluje linuxové binárky (esbuild, oxlint); kdyby ležely v adresáři sdíleném
s macOS, kolidovaly by s případnou lokální instalací.

### Tailwind CSS v4 přes `@tailwindcss/vite`
Oficiální Vite plugin – žádná konfigurace PostCSS ani `tailwind.config.js`. Tailwind i Vite jsou
v `devDependencies`, protože do prohlížeče jde jen výsledné CSS.

### oxlint místo ESLintu
Aktuální šablona `create-vite` už místo ESLintu používá oxlint – jeden balíček místo pěti,
výrazně rychlejší, pravidla pro React hooks obsahuje. Varování jsou chyby (`--deny-warnings`).
Odebrané `@types/react*` ze šablony: v JavaScriptovém projektu je nic nekontroluje.

### PHPStan level 8
Statická analýza odhalí chyby typů (null, špatné návratové typy) dřív než testy. Level 8
vyžaduje ošetření `null` a je u nového projektu bez dluhu snadno udržitelný.

### React Router zatím ne
Stránka je jedna; Router se přidá s druhou stránkou (prohlížení podle dne a měsíce).

## Krok 3 – databáze a import událostí (2026-10-29)

### Nextras Migrations přes vlastní službu, bez DI extension a bez Symfony Console
`App\Core\Database\Migrations` sestaví Runner, skupinu `structures` a SQL handler explicitně –
je vidět, co se děje, a stejná třída umí vypsat čekající migrace (i na prázdné DB, kde ještě
není tabulka `migrations`). Konzolový printer Nextras chybu znovu vyhodí, takže `make migrate`
při chybě skončí nenulovým kódem.

### Kontrola čekajících migrací jen ve webovém vstupu
`Bootstrap::bootWebApplication()` ji spouští, `createContainer()` (CLI, testy) ne – kontrola
tak nikdy nezablokuje `make migrate`. Produkce odpoví 503 a důvod zapíše do logu, development
jen varuje.

### Validace konfigurace přes Nette Schema před sestavením kontejneru
Z proměnných prostředí vznikne neměnný `AppConfig`; chybějící nebo špatná hodnota zastaví start
se seznamem všech problémů najednou. `APP_ENV` musí být výslovně `development` nebo `production`
(dřív „cokoli jiného = produkce“) – překlep se tak neschová.

### Rozhraní zdrojů (`DayPageSource`, `ArticleInfoSource`) a služby registrované pod nimi
Import nezná Wikipedii, jen rozhraní; testy dosadí zdroje z fixtures. Služby jsou v DI
registrované pod typem rozhraní, takže ostatní kód na konkrétní implementaci záviset nemůže.

### HTML konkrétní revize a ověření hlavičky
Revize stránky dne se zjistí z Action API a Parsoid HTML se načte pro **tu revizi**
(`/page/html/{titul}/{revize}`). REST endpoint nekontroluje, že revize patří k titulu
(revize 1 jiné stránky vrátila 200), proto se porovnává `Content-Revision-Id`.

### Feed „On this day“ v tomto kroku nepoužit
Revize, ID stránky, Wikidata ID i přesměrování dá Action API po 50 titulech jednoznačně;
feed by přidal druhou cestu kódu a párování textů. Zapojí se s úvody článků.

### Parser: co neumí přečíst, odmítne celý den
Nerozpoznaná položka v sekci Události (bez roku, rok bez textu, rok 0, neznámá podsekce,
položka mimo Česko/Svět) je chyba dne, ne přeskočený řádek – jinak by uložená událost
dostala `missing` kvůli chybě parseru. Výjimkou je jen úplně prázdná položka (`*` bez textu).
Pojistka „stránka ztratila víc než polovinu aktivních událostí“ je jen doplněk této kontroly.

### Rodič s vlastním textem a podbody = jedna událost
„1992 ve 23:59 končí existence Československa…:“ s podbody (zaniká ČSFR; zanikají ČST…) je
jedna věcná událost. Rodič jen s rokem („1918“) naopak sdružuje nezávislé události.

### Roky př. n. l.
`-1` = 1 př. n. l., `-45` = 45 př. n. l., rok 0 neexistuje (parser ho odmítne, DB má
`CHECK (year <> 0)`).

### Identita události: vlastní ID, párování podle textu, nic se nemaže
Wikipedie nedává stabilní ID události, proto má každá událost vlastní `id` (na ně se odkazují
oblíbené). **Podobnost pomáhá udržet identitu, neověřuje pravdivost.** Při importu se položky
párují v rámci dne + sekce + roku:
1. **přesná shoda** normalizovaného textu (velikost písmen, mezery),
2. **návrat původního textu**: skupina k revizi, jejíž původní událost je zpět s přesně svým
   textem a žádná náhrada už na stránce není, se vyřeší sama – původní události jsou opět
   `active` (stejné ID), náhrady `missing` (typicky vrácený vandalismus),
3. **podobná věta** – jen vzájemně nejlepší dvojice 1:1 s odstupem ≥ 0,15 od druhého kandidáta
   a podobností ≥ 0,70, nebo ≥ 0,60 při stejném hlavním článku,
4. co zbude, se **neodhaduje**: když v téže sekci a roce zároveň přibyde nová položka a zmizí
   (nebo už čeká na revizi) uložená, jdou všechny do jedné skupiny k revizi – **i při
   podobnosti pod 0,30**. Uložené dostanou roli `original`, nové `replacement`, výběr je ignoruje.
   Jen nová položka bez zmizení = nová událost; jen zmizení = `missing`.

Zmizelá událost dostane `missing` (+ `missing_since`), nemaže se; když se vrátí, je opět
`active`. Dávno zmizelá (`missing`) událost se do revize přidá jen při podobnosti ≥ 0,30
s novou položkou, jinak by blokovala každé pozdější přidání v tom roce. Události v revizi se
párují jen přesně, proto opakovaný import téže revize nevytvoří další záznamy k revizi
(test `DayImportTest`).

### Práh podobnosti nastaven podle skutečných úprav
Podobnost = vyšší z (a) znakové podobnosti (`similar_text`, zachytí překlepy a drobné úpravy)
a (b) překryvu slov (zachytí přeházené pořadí). Shoda hlavního článku nemůže být podmínkou:
ve **13 ze 40** jednoznačných úprav ze 150 revizí tří stránek (28. 10., 1. 1., 17. 11.) se hlavní
článek změnil (oprava přesměrování, přejmenování, nově přidaný odkaz).

Vyhodnocení na 4 dvojicích revizí ve fixtures (`tests/fixtures/wikimedia/history/`, regresní
test v `EventMatcherTest`):

| Den, revize | Rok | Úprava | Podobnost | Stejný článek | Výsledek |
|---|---|---|---|---|---|
| 17. 11., 23525658 → 23526886 | 1945 | interpunkce („Sb.“ → „Sb.,“) | 1,00 | ano | spárováno |
| | 1952 | přeformulování, přehozené pořadí vět | 0,71 | ano | spárováno |
| | 1968 | „university za podporu progresivních reforem“ → „vysoké školy na podporu reforem“ | 0,72 | ne (přibyl odkaz) | spárováno |
| | 1989 | „zásah policie … provázel“ → „zásah Veřejné bezpečnosti … odstartoval“ | 0,83 | ano | spárováno |
| | 1989 | polární záře – zkrácení věty | 0,69 | ano | spárováno |
| | 1989 | obnova sociální demokracie – přepsání | 0,61 | ano | spárováno |
| | 1999 | doplněno jméno | 0,88 | ano | spárováno |
| | 2014 | Albertov – přepsání | 0,61 | ano | spárováno |
| | 1939 | jedna událost rozdělena do dvou nových (nejvyšší podobnost 0,23) | – | – | k revizi (všechny 3) |
| 1. 1., 23905020 → 23959580 | 1990 | amnestie – přeformulování | 0,87 | ano | spárováno |
| | 1993 | přehozený slovosled | 0,67 | ano | spárováno |
| | 2000 | doplněno „V“, „samosprávných“ | 0,83 | ano | spárováno |
| | 2011 | přehozený slovosled | 1,00 (slova) | ano | spárováno |
| | 2016 | „CHKO“ → „Chráněná krajinná oblast“ | 0,69 | ano | spárováno |
| | 1992 | **oprava faktu** (zánik ČST → vznik ČT) | 0,50 | ne | k revizi |
| | 1996 | přepsání „Vznikl 76. okres Jeseník.“ na delší větu | 0,35 | – | k revizi |
| | 1990 | Havlův projev odstraněn (vůči amnestii 0,42, ta má vlastní pár) | – | – | `missing` |
| | 1983 | nová událost | – | – | nová |
| 28. 10., 23260912 → 23272755 | 1923 | **oprava faktu** (let Praha–Bratislava → oblet letiště) | 0,51 | ano | k revizi |
| 1. 1., 24784971 → 24807572 | 1770 | **vandalismus** („…si prdla a byla zabita…“) | 0,36 | – | k revizi |

Proč tyto prahy:
- **0,70** automaticky: nejnižší správný pár bez shody článku má 0,72; nejvyšší podobnost
  dvou *různých* vět ve vzorku je 0,42 – rezerva 0,28.
- **0,60 se stejným článkem**: přeformulované věty o stejném článku mají 0,61–0,71; oprava faktu
  se stejným článkem (1923) má 0,51 a do revize patří.
- **revize bez dolní hranice**: první návrh (kandidát od 0,40, později 0,30) posílal
  vandalismus (0,36) a přepsaný okres (0,35) do „nová událost + zmizelá“ – vandalismus by tak
  byl *aktivní* událostí. Nízká podobnost ale neznamená, že nová položka je v pořádku, proto
  současné zmizení a přidání ve stejném roce jde do revize vždy.
- **0,30 jen pro dávno zmizelé události**: krátké nesouvisející věty mají kvůli shodným
  písmenům podobnost až 0,32 („Zavřeny vysoké školy.“ × „Ve Vídni zemřel malíř.“), takže
  hranice chybuje na bezpečnou stranu (revize navíc), ne opačně.

### Zámek dne přes `GET_LOCK` v MariaDB
Transakce sama nezabrání dvěma souběžným importům téhož dne vložit stejnou novou událost.
Pojmenovaný zámek `tento_den.import.MM-DD` patří spojení, uvolní se i při pádu procesu; import,
který zámek nedostane, skončí chybou a stav dne nemění.

### Výpadek ≠ úspěch
Vše, co může selhat (síť, parser, pojistka), proběhne před transakcí; data dne a stav „ok“ se
zapíšou v jedné transakci. Chyba zapíše jen `error` a čas pokusu, uložené události i údaje
posledního úspěchu (revize, počet, čas) zůstanou.

### HTTP: vlastní klient nad cURL, slušnost jako dekorátor
`CurlHttpClient` (timeout, gzip, User-Agent s kontaktem) a `PoliteHttpClient` (sériově nejvýš
200 požadavků/min, 429/503 podle `Retry-After`, jinak exponenciálně od 5 s). `maxlag` hlásí API
jako HTTP 200 s `error.code = "maxlag"` v těle – kontroluje ho `ActionApiClient`. Čas a čekání
přes rozhraní `Clock`, testy neusínají.

### Hlavní článek a seznam obecných článků
První odkaz, který není rok, datum ani obecný článek z `config/generic-articles.neon`
(25 článků odkazovaných z ≥ 48 událostí za rok, docs/zdroje.md). `articles:generic-candidates`
jen vypisuje četnost odkazů – automaticky nic neoznačuje, výsledek by závisel na tom, kolik dní
je naimportováno.

### Minimální konzole bez frameworku
`ConsoleApplication` jen vybere příkaz, rozparsuje `--volba[=hodnota]` a chybu argumentů převede
na exit code 2. Příkazy jsou DI služby s rozhraním `Command`.

### Testovací databáze `<DB_NAME>_test`, test hygieny v jednorázovém kontejneru
`make up` ji idempotentně založí (root heslo zůstává v kontejneru MariaDB). DB testy se řadí za
sebou přes zámek Testeru. Test hygieny potřebuje celý repozitář a git, a tedy by viděl i `.env`
s root heslem – proto běží v jednorázovém kontejneru s repozitářem jen pro čtení, ne v běžící
php službě.

### `bin/init-env.php` v PHP kontejneru
Lokálně PHP není a Compose bez `.env` nenastartuje, proto `make init-env` spouští skript
v samostatném kontejneru pod UID uživatele (soubor patří jemu, práva 0600 od vytvoření).

### Známá omezení (stav po kroku 3)
- **Legitimní rozdělení události skončí v revizi.** Když editor jednu událost rozdělí na dvě
  (17. 11. 1939: pohřeb Jana Opletala → uzavření vysokých škol + zatýkání studentů), import
  to nerozliší od vandalismu nebo záměny – původní i obě nové události dostanou `needs_review`
  a **do vyřešení se nevybírají**. Vyřešení zatím jen ručně (`events:review` vypíše skupinu;
  příkaz na sloučení/přijetí přijde později).
- **Hlavní článek bývá kontext, ne předmět události.** Pravidlo „první odkaz, který není rok,
  datum ani obecný článek“ vybere např. pro bitvu u Milvijského mostu *Starověký Řím*, pro
  založení Harvardu *Cambridge (Massachusetts)*, pro vyhlášení války Japonsku *Čínu*.
  Nové heuristiky zatím záměrně nepřidáváme; hlavní článek proto nelze brát jako spolehlivý
  signál výběru.
- **Stránky dnů s nestandardním obsahem se neimportují** (striktní parser). Ověřeno 29. 10. 2026:

  | Den | Důvod chyby |
  |---|---|
  | 6. 1. | podsekce „Pravidelné události“ (Tříkrálová sbírka, bez roku) |
  | 17. 4. | podsekce „Hospodářství a ekologie“; navíc rok slovy („360 před naším letopočtem“) |
  | 30. 6. | řádek bez roku ve Světě („Jedno z dat, kdy je … přestupná sekunda“) |
  | 14. 12. | řádek bez roku v Česku (Tatra 148 – rok chybí) |

  Tyto dny mají v `import_days` stav `error` s důvodem; jejich ošetření (oprava na Wikipedii,
  nebo výslovná výjimka v konfiguraci) je otevřené rozhodnutí.

## Krok 3b – výjimky pro nestandardní stránky dnů (2026-10-29)

### Dvě úzká, výslovná pravidla místo obecného přeskakování
`config/day-page-rules.neon`:
1. **ignorované podsekce podle přesného názvu** („Pravidelné události“ 6. 1., „Hospodářství
   a ekologie“ 17. 4.); jakákoli jiná neznámá podsekce dál shodí celý den,
2. **známé položky bez roku podle dne + otisku textu** (SHA-256 normalizovaného textu, 30. 6.
   a 14. 12.); jakmile se text změní, otisk nesedí a den znovu selže. Otisk vypíše chybová
   hláška importu, takže položka se do seznamu dostane jen vědomým rozhodnutím.

Každé přeskočení se zaloguje (`log/info.log`, s dnem a revizí) a import ho vypíše v souhrnu.

**Proč ne obecné „neznámé přeskoč a pokračuj“:** parser by pak tiše vynechal i položku, kterou
jen špatně přečetl (nový formát roku, rozbitý řádek), a párování by uloženou událost označilo
jako `missing` – chyba parseru by se tvářila jako editace Wikipedie a mazala by obsah, na který
můžou odkazovat oblíbené. Výslovné pravidlo naopak říká, *co přesně* a *proč* vynecháváme,
a každá nová odchylka se projeví chybou dne, kterou lze posoudit.

**Oprava (krok 3c):** „Hospodářství a ekologie“ (17. 4.) obsahuje 13 datovaných světových
událostí, ignorování by je zahodilo. Nahrazeno třetím úzkým pravidlem – **mapováním podsekce
na sekci jen pro daný den** (`mappedSubsections`: 17. 4. → Svět). Stejný nadpis v jiném dni je
dál neznámá podsekce. Duplicity se Světem 17. 4. zkontrolovány: nejvyšší podobnost 0,42 (jiné
roky), shodný text žádný; texty se přebírají beze změny.

### Rok slovy „před naším letopočtem“
Parser čte vedle „45 př. n. l.“ i „360 před naším letopočtem“ (obojí → záporný rok).

### Konzole loguje do `log/` jako web
Do kroku 3 měl Tracy logger adresář jen ve webovém vstupu; první zalogované přeskočení v CLI
shodilo import („Logging directory is not specified“). `bin/console.php` a `bin/migrate.php`
proto startují přes `Bootstrap::bootConsoleApplication()` (Tracy s `log/`, bez kontroly migrací);
testy používají `createContainer()` a logger si nahrazují pamětí.

## Fáze A – výběr pětice a API (2026-10-29)

### Signál: počet jazykových verzí z Wikidat (`wbgetentities`, `props=sitelinks`)
50 položek na dotaz (218 dotazů na 10 887 článků, ~2 minuty se slušným klientem). Stejný HTTP
klient jako import (User-Agent s kontaktem, 200 req/min, `Retry-After`, `maxlag`). Jediné
neexistující ID shodí celou dávku (`no-such-entity` s `id` v těle) – zdroj ID zaznamená jako
nenalezené a dávku zopakuje bez něj. Ukládá se po dávkách, přerušený běh pokračuje chybějícími.

### Skóre a normalizace
Sitelinks hlavního článku, jinak nejvyšší sitelinks neobecných odkazů. `log(1 + s) / log(1 + max)`
v rámci **dne a sekce** – česká událost se 8 jazyky (Vznik Československa) se nesrovnává se
Sochou Svobody (151). Čistá funkce (`EventScorer`), testy bez DB.

### Filtr nudy jako čitelný seznam pojmenovaných pravidel (`config/selection.neon`)
Úzká pravidla (sportovní výsledek, skóre zápasu, otevření obchodu, otevření stavby, vydání
softwaru, nehoda bez obětí). Nudná událost se jen řadí za ostatní, nikdy nemizí. Výjimka pro
články s ≥ 50 jazyky byla **odstraněna**: hlavní článek bývá kulisa, takže házená ve Švédsku
„měla“ 296 jazyků článku *Švédsko* a filtrem prošla (zachyceno v reportu kvality).

### Pětice se počítá při každém dotazu, neukládá se
Je deterministická (pořadí: nenudná, skóre, ID), takže stejný den = stejná pětice bez tabulky
výběrů a bez invalidace po importu nebo změně signálů. Pravidla: aspoň 2 české (pokud jsou),
nudné až nakonec. (Původní „každá z jiného století“ – viz níže – bylo nahrazeno.) `sensitive` je jen vlastnost karty
(regulární výrazy v `config/selection.neon`), výběr neovlivňuje.

### API: request DTO → `DayService` → repository → response DTO
`ApiException` nese zprávu bezpečnou pro klienta (400/404/405); jiné chyby dostanou jen obecný
text. Bezpečnostní hlavičky pro JSON API: `Content-Security-Policy: default-src 'none'`,
`X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `nosniff`, `Cross-Origin-Resource-Policy`.
Odkaz na hlavní článek vede na jeho uloženou revizi (dohledatelnost).

## Fáze B – frontend (2026-10-29)

### Nové závislosti
- `react-router` – trasy den / oblíbené a adresy sdílených karet (`/den/MM-DD#karta-ID`),
- `lucide-react` – ikony (SF Symbols nesmí mimo platformy Apple),
- `vitest`, `@testing-library/react`, `jsdom` (dev) – testy komponent v prostředí podobném prohlížeči.

### Datová vrstva s dvěma režimy (`src/data/days.js`)
`VITE_DATA_MODE=static` čte `public/data/MM-DD.json` (stejný formát jako `GET /api/day`),
jinak živé API. Komponenty o režimu nevědí. Odpověď se kontroluje (datum, pole karet);
chyba sítě i HTTP chyba jsou chybový stav, nikdy prázdný den.

### Bezpečnost ve frontendu
Texty jen jako text (žádné `dangerouslySetInnerHTML`), odkazy vykreslí jen pro `https://*.wikipedia.org`
(licence pro `creativecommons.org`) s `rel="noopener noreferrer"`. Test podstrčí HTML a cizí odkaz.

### Oblíbené jako snímek karty v `localStorage`
Každý přístup v `try/catch`; bez úložiště appka funguje s oblíbenými v paměti a upozorní na to.
Ukládá se kopie karty, takže seznam funguje i ve statickém demu bez `/api/events/{id}`.

### Vzhled pro iPhone
Velký titulek, který se při scrollu schová do průsvitné lišty (IntersectionObserver),
`backdrop-filter` s neprůhledným fallbackem, `env(safe-area-inset-*)`, tmavý režim podle systému,
dotykové plochy ≥ 44 px, animace jen `motion-safe`.

### Screenshoty přes statický build v iframu 390 px
Headless Chrome má minimální šířku okna 500 px; aplikace se proto vkládá do iframu široké 390 px
a obrázek se ořízne. Snímky z dev serveru Vite občas zachytily stav načítání, proto ze statického
buildu.

## Fáze C – demo na GitHub Pages (2026-10-29)

### Statická data v repozitáři, build v GitHub Actions
`export:static` zapíše pětice 366 dní (1,4 MB) do `frontend/public/data/`; jsou commitnuté
(s poznámkou o CC BY-SA), aby workflow nepotřeboval backend ani databázi. Workflow spustí lint
a testy, postaví statický režim s `base` z `configure-pages`, zkopíruje `index.html` jako
`404.html` (Pages nemá SPA fallback) a nasadí. Oprávnění jen po jobech (čtení / pages + id-token).

## Redesign frontendu – „almanach dne“ (2026-10-29)

### Barva dne podle ročního období
Každý den dostane odstín (`--day-hue`) plynule interpolovaný přes rok: zimní modrá → jarní zelená →
letní zlatá → podzimní oranžová → zimní fialová. Odstíny jsou v OKLCH, takže světlost (a tím kontrast)
je stejná pro všechny barvy. Barví titulek, roky, aktivní ovládání a jemnou „auroru“ v pozadí –
appka vypadá každý den trochu jinak bez jakýchkoli dat navíc.

### Typografie bez externích fontů
`ui-serif` (na Apple zařízeních New York) pro data, roky a texty událostí – redakční charakter;
SF Pro / `ui-rounded` pro ovládání. Žádné stahování fontů, žádné SF Symbols (ikony Lucide).

### Liquid Glass a hierarchie
Plovoucí skleněné kapsle (navigace dnů, spodní lišta, kompaktní titulek po odscrollování)
s `backdrop-filter` a neprůhledným fallbackem. Na desktopu dvousloupec s lepkavým hero panelem.
Citlivé karty nemají barevný nádech ani barevný rok.

### Přístupnost ověřená výpočtem, ne odhadem
Kontrast všech dvojic barev (text na kartě, papíru, barevném nádechu, aktivní záložka, tlačítko
Dnes) spočítán převodem OKLCH → sRGB pro 12 odstínů roku v obou režimech: minimum 5,14 : 1
(AA vyžaduje 4,5 : 1). Viditelný focus v barvě dne, dotykové plochy ≥ 44 px, všechny animace
(nástup karet, „pop“ srdíčka, pohyb aurory) vypnuté při `prefers-reduced-motion`.

### Atribuce zůstává viditelná
Rozbalovací `<details>` by v zavřeném stavu skryl odkazy i čtečkám a testům, proto je atribuce
decentní řádek pod kartou: „Wikipedie, 28. říjen“ a odkazy Zdroj · autoři · CC BY-SA 4.0.

### Pozadí jen na `html`
Aurora je vrstva se záporným `z-index`; vlastní pozadí `body` by ji podle pořadí vykreslování
CSS překrylo, proto barvu papíru nese jen `html`.

### Screenshoty přes Chrome DevTools Protocol
Skutečná emulace iPhonu (390 × 844, 3×) a `prefers-color-scheme`, čekání na vykreslené karty.
`--screenshot` v headless Chromu měl minimální šířku 500 px a s nekonečnou animací pozadí
fotil prázdnou stránku.

## Rozmanitost století jen při shodném skóre (2026-10-29)
Původně měla každá karta v prvním průchodu pocházet z jiného století. To předbíhalo skóre:
ze zlaté sady 8 výročí bylo v pětici jen 1, u ostatních 7 rozhodlo „20. století už v pětici je“.
Nově je pořadí kandidátů: nenudná před nudnou → vyšší skóre → **při shodném skóre** dosud
nezastoupené století → nižší ID; dvě česká místa se plní stejným pořadím jako první. Zlatá sada:
2 z 8; zbylé chybí kvůli signálu (hlavní článek je kulisa nebo méně známý článek), ne kvůli
pravidlu – vyřeší se v plné verzi výběrem hlavního článku.

## Experiment s kvótami a penalizací obecných článků – nepřijat (2026-10-29)
Pevné kvóty 2 + 3 a penalizace článků podle počtu odkazujících událostí byly změřeny zvlášť
i dohromady na zlaté sadě a 12 dnech ([experiment-vyberu.md](experiment-vyberu.md)). Penalizace
zlepšila zlatou sadu (3 z 8 místo 2), ale na 12 dnech byla horší (68 vs. 73 bodů ručního
hodnocení); kvóty byly horší na dnech a stejné na sadě. Ponecháno jen to, co je lepší na obou –
tedy nic; kód variant odstraněn. Zlatá sada se nově identifikuje konkrétním textem události,
ne rokem a sekcí.

## Deset stejných karet od nejnovější (2026-10-29)

- **10 karet, aspoň 3 české:** stejná pravidla výběru jako u pětice (nenudné → skóre → století
  jen při shodě → ID), bez omezení stáří. Zlatá sada: 4 z 8 v desítce (v pětici 2 z 8).
- **Pořadí od nejnovější po nejstarší** v API, statickém exportu i na stránce – současnost
  čtenáři bývá bližší; výběr se pořadím nemění.
- **Všechny karty stejné** (rok, „před X lety“, štítek, celý text, článek, atribuce) a štítek
  sekce jen jako text – žádná „hlavní“ karta, žádné symboly ani přepínač.
- **Bez časové osy, bubliny, glóbu a AI titulků.** Vyzkoušené varianty (časová osa dne, bubliny
  nad vektorovým glóbem s obrysem Česka, AI titulky s automatickou kontrolou jmen a čísel) jsou
  uložené ve větvi `experiment/bubliny`. Důvod: další závislosti a mapová data, pomalejší první
  vykreslení (Lighthouse mobile výkon 82, tato verze 93) a AI vrstva, kterou by bylo třeba trvale hlídat.
  Na `main` z nich nezůstala žádná závislost, tabulka ani migrace.

## Státní svátky a významné dny podle zákona (2026-10-29)

- **Zdroj je zákon, ne ruční výběr.** Seznam (`config/state-days.neon`) je přesně § 1, § 2 a § 4
  zákona č. 245/2000 Sb. v aktuálním znění (ověřeno na zakonyprolidi.cz, verze 12 k 13. 5. 2026):
  oficiální názvy, typ, odkaz na paragraf. Ruční „přišpendlení“ oblíbených událostí by bylo
  subjektivní a neověřitelné; zákon je oficiální, úplný a každý si ho může zkontrolovat.
- **Viditelný štítek.** Událost, kterou svátek připomíná, je v desítce i s nízkým skóre – proto
  nese štítek „Státní svátek“ / „Významný den“ a oficiální název. Čtenář vidí, proč tam je; skóre
  se tím nepřepisuje.
- **Párování přes úryvek, nic se neodhaduje.** U dne s historickou událostí je rok a úryvek textu;
  musí najít právě jednu aktivní událost dne. Jinak se nic nevynucuje a report (`docs/kvalita.md`)
  ukáže chybu. Kterou událost den připomíná, je ověřené podle článku „Státní svátky Česka“
  (sloupec „Připomíná“); když stránka dne tu událost nemá (Den samizdatu připomíná protest z roku
  1988, Mezinárodní den žen demonstraci z roku 1917), dostane den kartu s článkem.
- **Karta svátku bez události** má úvod článku z REST `page/summary` (uložený s revizí, atribuce
  CC BY-SA) a do desítky se nepočítá. Úvod se nezkracuje (byla by to úprava zdrojového textu),
  dlouhý se jen vizuálně složí na pět řádků s tlačítkem „Celý úvod“.
- **Pohyblivé svátky** (Velký pátek, Velikonoční pondělí) se počítají z data Velikonoc
  (gregoriánský algoritmus) a ukazují se jen, když je známý rok: v API parametr `year`, bez
  data „dnes“ včetně roku; statický export má `movable.json` podle přesných dat na 10 let dopředu.
- **Odkaz na zákon jen jako text** na kartě („podle zákona č. 245/2000 Sb.“): odkazy v aplikaci
  vedou jen na Wikipedii; odkaz na zákon je v README a v konfiguraci.
- Golden set: 6 z 8 v desítce (28. 10. 1918 a 17. 11. 1989 díky státním svátkům).
