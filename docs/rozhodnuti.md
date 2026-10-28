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
