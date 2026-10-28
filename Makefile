COMPOSE := docker compose

.PHONY: up down test lint check-env

## Build images, install dependencies and start everything (waits until healthy)
up: check-env
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps php composer install --no-interaction
	$(COMPOSE) run --rm --no-deps node npm ci
	$(COMPOSE) up -d --wait

## Stop and remove containers (data in volumes is kept)
down:
	$(COMPOSE) down

## Backend tests (Nette Tester) against the running database
test:
	$(COMPOSE) exec php vendor/bin/tester -C tests

## Static analysis (PHPStan level 8) and frontend lint (ESLint)
lint:
	$(COMPOSE) exec php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
	$(COMPOSE) exec node npm run lint

check-env:
	@./scripts/check-env.sh .env
