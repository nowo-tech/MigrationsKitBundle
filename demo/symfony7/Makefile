# Makefile for Migrations Kit Bundle Demo - Symfony 7
# Run from this directory: make up, make install, make migrate, etc.

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build install shell logs setup migrate migrate-dry-run migrate-write-sql cache-clear update-bundle ensure-up test test-coverage

help:
	@echo "Migrations Kit Bundle Demo - Symfony 7"
	@echo ""
	@echo "  make up                Start container + PHP server (http://localhost:$${PORT:-8007})"
	@echo "  make down              Stop container"
	@echo "  make build             Build image (no cache)"
	@echo "  make install           Install dependencies in container"
	@echo "  make migrate           Create DB (if needed) and run migrations"
	@echo "  make migrate-verbose   Run migrations and show SQL on screen (-vv)"
	@echo "  make migrate-dry-run   Show SQL that would run (no changes applied)"
	@echo "  make migrate-write-sql Write pending migration SQL to var/migration.sql"
	@echo "  make setup             install + migrate"
	@echo "  make shell             Open shell in PHP container"
	@echo "  make logs              Show container logs"
	@echo "  make cache-clear       Clear Symfony cache"
	@echo "  make update-bundle     Update bundle from path repo"
	@echo "  make test              Run tests"
	@echo "  make test-coverage     Run tests with coverage (console output)"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || (echo "APP_ENV=dev" > .env; echo "APP_SECRET=change-me" >> .env; echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env); fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && composer migrate'
	@echo "App: http://localhost:$${PORT:-8007} (schema sync status)."

down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

migrate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && composer migrate'

migrate-verbose: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php scripts/ensure-database.php; php bin/console doctrine:migrations:migrate --no-interaction -vv'
	@echo "(-vv shows SQL executed per migration)"

migrate-dry-run: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php bin/console doctrine:migrations:migrate --dry-run --no-interaction -vv'
	@echo "Tip: use -vvv for full debug (all SQL shown)."

migrate-write-sql: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php bin/console doctrine:migrations:migrate latest --write-sql=var/migration.sql --no-interaction'
	@echo "SQL written to var/migration.sql (if there were pending migrations)."

setup: install migrate
	@echo "Demo ready. Tables created in var/data.db"

shell: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) sh

logs:
	$(COMPOSE) logs -f

cache-clear: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer update nowo-tech/migrations-kit-bundle --no-interaction
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear
	@echo "Bundle updated."

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) php bin/console list

test-coverage: ensure-up
	$(COMPOSE) exec -e APP_ENV=test $(SERVICE_PHP) composer test-coverage 2>/dev/null || $(COMPOSE) exec $(SERVICE_PHP) composer test
