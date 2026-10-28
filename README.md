# Tento den

Každý den pět zajímavých výročí z Wikipedie – ze světa i z Česka.
Ukázkový projekt: **PHP 8.3 + Nette 3.2** (REST API) a **React + Vite + Tailwind** (SPA).

Popis projektu a pravidla vývoje: [CLAUDE.md](CLAUDE.md). Architektonická rozhodnutí:
[docs/rozhodnuti.md](docs/rozhodnuti.md).

```
tento-den/
├── backend/
│   ├── app/Api/        controllery (Nette presentery jako API endpointy)
│   ├── app/Model/      služby, repozitáře, DTO (Events, Import, Wikipedia, Health)
│   ├── app/Console/    konzolové příkazy (import)
│   ├── app/Core/       infrastruktura (konfigurace, HTTP, DB, konzole)
│   ├── migrations/     SQL migrace (Nextras Migrations)
│   └── bin/            console.php, migrate.php
├── frontend/    React SPA (Vite, Tailwind)
├── docker/      Dockerfile pro PHP, nginx, skript testovací DB
├── docs/        dokumentace, zdroje dat a rozhodnutí
└── bin/         init-env.php, check-env.sh
```

## Požadavky

- Docker s Docker Compose v2
- `make`

PHP, Composer ani Node lokálně nepotřebujete – vše běží v kontejnerech.

## Spuštění

```bash
make init-env        # vytvoří .env s náhodnými hesly (existující .env nepřepíše)
# doplňte do .env WIKIMEDIA_CONTACT = kontaktní e-mail (posílá se v User-Agentu na Wikipedii)
make up
make migrate
make import-day DATE=10-28
```

- aplikace: <http://localhost:8080>
- API: <http://localhost:8080/api/health>
- MariaDB: `127.0.0.1:3307` (jen z tohoto počítače)

`make up` sestaví image, nainstaluje závislosti (Composer, npm), počká, až jsou všechny služby
zdravé, a založí testovací databázi. Bez `.env` nebo s prázdnými hesly či kontaktem odmítne start.
Web s neprovedenými migracemi v produkci nenastartuje (503), v developmentu jen varuje v logu.

## Příkazy

| Příkaz | Co dělá |
|---|---|
| `make up` | sestaví a spustí vše (nginx, php-fpm, MariaDB, Vite dev server) |
| `make down` | zastaví kontejnery (data databáze zůstanou ve volume) |
| `make init-env` | vytvoří `.env` z `.env.example` s náhodnými hesly |
| `make migrate` | provede čekající migrace databáze |
| `make import-day DATE=MM-DD` | importuje události jednoho dne z cs.wikipedia |
| `make import-year [RESUME=1]` | importuje všech 366 dní; `RESUME=1` naváže na poslední běh a zopakuje jen neúspěšné dny |
| `make test` | backendové testy (Nette Tester, testovací DB) a test hygieny repozitáře |
| `make lint` | PHPStan (level 8) a oxlint |

Další příkazy: `docker compose exec php php bin/console.php` vypíše seznam (např. `events:review` –
události, u kterých import nerozhodl, zda jde o úpravu jiné; `articles:generic-candidates` –
kandidáti na seznam obecných článků v `backend/config/generic-articles.neon`).

Ověřovací reporty po importu (jen čtou, nic neopravují), spouštějí se v php kontejneru
(`docker compose exec php php bin/report/…`):

| Skript | Co ověřuje |
|---|---|
| `import-stats.php` | počty dní a událostí, medián/minimum na den, přepočet hlavního článku jen z DB |
| `check-links.php [vzorek] [seed]` | shodu DB s výstupem **stejného** parseru na vzorku dní (stáhne HTML uložené revize) |
| `compare-wikitext-links.php [min. rozdíl]` | nezávislou hrubou kontrolu parseru: odkazy ve wikitextu vs. DB po dnech |

Po změně `docker/nginx/default.conf` je potřeba `docker compose restart nginx`.

## Proměnné prostředí (`.env`)

| Proměnná | Význam |
|---|---|
| `APP_ENV` | `development` (debug režim) nebo `production`; jiná hodnota = aplikace nenastartuje |
| `DB_NAME`, `DB_USER` | databáze a aplikační uživatel MariaDB |
| `DB_PASSWORD` | heslo aplikačního uživatele (povinné) |
| `DB_ROOT_PASSWORD` | root heslo MariaDB, dostane ho jen kontejner databáze (povinné) |
| `WIKIMEDIA_CONTACT` | kontaktní e-mail do User-Agentu požadavků na Wikimedia API (povinné) |
| `HTTP_PORT` | port aplikace na 127.0.0.1 (výchozí 8080) |
| `DB_HOST_PORT` | port MariaDB na 127.0.0.1 (výchozí 3307) |

`.env` se nikdy necommituje; v repozitáři je jen `.env.example`. Konfigurace se při startu
validuje (Nette Schema) a chyba vypíše všechny problémy najednou.

## API

| Metoda | Cesta | Odpověď |
|---|---|---|
| GET | `/api/health` | `200 {"status":"ok"}`, nebo `503 {"status":"error"}` když není dostupná databáze |

Chyby vrací API vždy jako JSON: `{"error":{"code":404,"message":"Not Found"}}`.
