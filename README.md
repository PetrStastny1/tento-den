# Tento den

Každý den pět zajímavých výročí z Wikipedie – ze světa i z Česka.
Ukázkový projekt: **PHP 8.3 + Nette 3.2** (REST API) a **React + Vite + Tailwind** (SPA).

Popis projektu a pravidla vývoje: [CLAUDE.md](CLAUDE.md). Architektonická rozhodnutí:
[docs/rozhodnuti.md](docs/rozhodnuti.md).

```
tento-den/
├── backend/     Nette REST API (app/Api = controllery, app/Model = služby, repozitáře, DTO)
├── frontend/    React SPA (Vite, Tailwind)
├── docker/      Dockerfile pro PHP, konfigurace nginx
├── docs/        dokumentace a rozhodnutí
└── scripts/     pomocné skripty (kontrola .env)
```

## Požadavky

- Docker s Docker Compose v2
- `make`

PHP, Composer ani Node lokálně nepotřebujete – vše běží v kontejnerech.

## Spuštění

```bash
cp .env.example .env
# vyplňte DB_PASSWORD a DB_ROOT_PASSWORD, např. hodnotou z: openssl rand -hex 24
make up
```

- aplikace: <http://localhost:8080>
- API: <http://localhost:8080/api/health>
- MariaDB: `127.0.0.1:3307` (jen z tohoto počítače)

`make up` sestaví image, nainstaluje závislosti (Composer, npm) a počká, až jsou všechny služby
zdravé. Bez `.env` nebo s prázdnými hesly odmítne start.

## Příkazy

| Příkaz | Co dělá |
|---|---|
| `make up` | sestaví a spustí vše (nginx, php-fpm, MariaDB, Vite dev server) |
| `make down` | zastaví kontejnery (data databáze zůstanou ve volume) |
| `make test` | backendové testy (Nette Tester) proti běžící databázi |
| `make lint` | PHPStan (level 8) a oxlint |

Po změně `docker/nginx/default.conf` je potřeba `docker compose restart nginx`.

## Proměnné prostředí (`.env`)

| Proměnná | Význam |
|---|---|
| `APP_ENV` | `development` zapne debug režim; cokoli jiného běží jako produkce |
| `DB_NAME`, `DB_USER` | databáze a aplikační uživatel MariaDB |
| `DB_PASSWORD` | heslo aplikačního uživatele (povinné) |
| `DB_ROOT_PASSWORD` | root heslo MariaDB, dostane ho jen kontejner databáze (povinné) |
| `HTTP_PORT` | port aplikace na 127.0.0.1 (výchozí 8080) |
| `DB_HOST_PORT` | port MariaDB na 127.0.0.1 (výchozí 3307) |

`.env` se nikdy necommituje; v repozitáři je jen `.env.example`.

## API

| Metoda | Cesta | Odpověď |
|---|---|---|
| GET | `/api/health` | `200 {"status":"ok"}`, nebo `503 {"status":"error"}` když není dostupná databáze |

Chyby vrací API vždy jako JSON: `{"error":{"code":404,"message":"Not Found"}}`.
