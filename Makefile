COMPOSE := docker compose
PHP_IMAGE := php:8.3-fpm-alpine

.PHONY: up down test lint check-env init-env migrate import-day import-year refresh-signals export-static

## Create .env with random passwords (never overwrites an existing one)
init-env:
	docker run --rm --user "$$(id -u):$$(id -g)" -v "$(CURDIR)":/app -w /app $(PHP_IMAGE) php bin/init-env.php /app

## Build images, install dependencies and start everything (waits until healthy)
up: check-env
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps php composer install --no-interaction
	$(COMPOSE) run --rm --no-deps node npm ci
	$(COMPOSE) up -d --wait
	$(COMPOSE) exec -T mariadb sh < docker/mariadb/create-test-db.sh

## Stop and remove containers (data in volumes is kept)
down:
	$(COMPOSE) down

## Run pending database migrations
migrate:
	$(COMPOSE) exec php php bin/migrate.php

## Backend tests (Nette Tester, test database), repository hygiene test, frontend tests (Vitest)
test:
	$(COMPOSE) exec php vendor/bin/tester -C tests
	$(COMPOSE) run --rm --no-deps -v "$(CURDIR)":/repo:ro -e REPO_ROOT=/repo php vendor/bin/tester -C tests/Hygiene
	$(COMPOSE) exec node npm test

## Static analysis (PHPStan level 8) and frontend lint (oxlint)
lint:
	$(COMPOSE) exec php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
	$(COMPOSE) exec node npm run lint

## Import one day: make import-day DATE=10-28
import-day:
	$(COMPOSE) exec php php bin/console.php import:day --date=$(DATE)

## Import all 366 days: make import-year [RESUME=1]
import-year:
	$(COMPOSE) exec php php bin/console.php import:year $(if $(RESUME),--resume)

## Refresh article signals (Wikidata sitelinks): make refresh-signals [ALL=1]
refresh-signals:
	$(COMPOSE) exec php php bin/console.php articles:refresh-signals $(if $(ALL),--all)

## Export the ten cards of all 366 days as static JSON into frontend/public/data/
export-static:
	$(COMPOSE) exec php php bin/console.php export:static --dir=temp/export
	rm -rf frontend/public/data && mkdir -p frontend/public/data
	cp backend/temp/export/*.json frontend/public/data/
	cp docs/data-license.md frontend/public/data/README.md

check-env:
	@./bin/check-env.sh .env
