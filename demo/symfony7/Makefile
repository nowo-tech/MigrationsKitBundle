# Makefile for Migrations Kit Bundle Demo - Symfony 7
# Run from this directory: make up, make install, make migrate, etc.

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

DATABASE_URL_MYSQL := mysql://app:app@mysql:3306/demo

.PHONY: help up down build install lock shell logs setup migrate migrate-verbose migrate-dry-run migrate-write-sql write-sql-reference-mysql db-reset cache-clear update-bundle ensure-up test test-coverage check-pdo-mysql up-mysql db-reset-mysql migrate-mysql test-mysql list-migrations

help:
	@echo "Migrations Kit Bundle Demo - Symfony 7"
	@echo ""
	@echo "  make up                Start container + PHP server (does NOT run migrations)"
	@echo "  make down              Stop container"
	@echo "  make build             Build image (no cache)"
	@echo "  make install           Install dependencies in container"
	@echo "  make lock              Generate composer.lock (run once if missing; needs ~4GB Docker memory)"
	@echo "  make migrate           Create DB (if needed) and run migrations (SQLite)"
	@echo "  make migrate-verbose   Run migrations with -vvv (SQL + debug)"
	@echo "  make migrate-dry-run   Show SQL that would run (no changes applied)"
	@echo "  make migrate-write-sql Write pending migration SQL to var/migration.sql"
	@echo "  make write-sql-reference-mysql  Reset MySQL, write all migration SQL to var/expected_migrations_mysql.sql"
	@echo "  make db-reset          Remove var/data.db (fresh DB for next migrate)"
	@echo "  make up-mysql          Start PHP + MySQL 8 (for MySQL tests)"
	@echo "  make db-reset-mysql    Drop and recreate MySQL database (fresh for migrate-mysql)"
	@echo "  make migrate-mysql     Run migrations against MySQL 8"
	@echo "  make test-mysql        update-bundle + db-reset-mysql + migrate-mysql (one command)"
	@echo "  make setup             install + migrate"
	@echo "  make shell             Open shell in PHP container"
	@echo "  make logs              Show container logs"
	@echo "  make cache-clear       Clear Symfony cache"
	@echo "  make update-bundle     Update bundle from path repo"
	@echo "  make test              Run tests"
	@echo "  make test-coverage     Run tests with coverage (console output)"
	@echo "  make list-migrations   Print migration execution order and what each step does/validates"
	@echo ""

list-migrations: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php scripts/list-migrations-order.php

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || (echo "APP_ENV=dev" > .env; echo "APP_SECRET=change-me" >> .env; echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" >> .env); fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be running (up to 30s)..."
	@for i in 1 2 3 4 5 6 7 8 9 10; do \
		if $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then break; fi; \
		if [ $$i -eq 10 ]; then echo "Container not ready. Try: make down && make up"; echo "Then: make logs"; exit 1; fi; \
		echo "  waiting... ($$i/10)"; sleep 3; \
	done
	@$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var/cache/dev var/log'
	@if [ ! -f composer.lock ]; then \
		echo "No composer.lock found. Generating it in a one-off container (needs ~4GB Docker memory)."; \
		$(COMPOSE) run --rm --entrypoint "" -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer update --no-install --no-interaction --no-scripts --prefer-dist || (echo "► If exit 137: give Docker 4GB+ RAM (Docker Desktop → Settings → Resources), then run: make lock"; exit 1); \
	fi
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var'
	@echo "App: http://localhost:$${PORT:-8007}. make up does NOT run migrations; run 'make migrate' to apply them."

down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

lock:
	@echo "Generating composer.lock (one-off container; ensure Docker has 4GB+ memory to avoid exit 137)."
	$(COMPOSE) run --rm --entrypoint "" -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer update --no-install --no-interaction --no-scripts --prefer-dist
	@echo "composer.lock created. Run 'make up' or 'make install'."

ensure-up:
	@if $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then exit 0; fi; \
	echo "Starting container..."; \
	$(COMPOSE) up -d; \
	echo "Waiting for container to be running (up to 30s)..."; \
	for i in 1 2 3 4 5 6 7 8 9 10; do \
		if $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then break; fi; \
		if [ $$i -eq 10 ]; then \
			echo "Container not ready. If it is restarting, run: make down && make up"; \
			echo "Then: make logs  (to see why it crashes)"; \
			exit 1; \
		fi; \
		echo "  waiting... ($$i/10)"; sleep 3; \
	done; \
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

migrate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php -d memory_limit=256M scripts/ensure-database.php 2>/dev/null; php -d memory_limit=256M bin/console doctrine:migrations:migrate --no-interaction -vvv'

migrate-verbose: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php scripts/ensure-database.php; php bin/console doctrine:migrations:migrate --no-interaction -vvv'
	@echo "(-vvv: debug, SQL and stack traces on error)"

migrate-dry-run: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php bin/console doctrine:migrations:migrate --dry-run --no-interaction -vv'
	@echo "Tip: use -vvv for full debug (all SQL shown)."

migrate-write-sql: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c 'mkdir -p var && php bin/console doctrine:migrations:migrate latest --write-sql=var/migration.sql --no-interaction'
	@echo "SQL written to var/migration.sql (if there were pending migrations)."

write-sql-reference-mysql: ensure-up check-pdo-mysql
	$(COMPOSE) up -d mysql
	@echo "Waiting for MySQL..."
	@for i in 1 2 3 4 5 6 7 8 9 10; do \
		if $(COMPOSE) exec -T mysql mysqladmin ping -h localhost -u root -proot 2>/dev/null; then break; fi; \
		sleep 2; \
	done
	$(COMPOSE) exec -T mysql mysql -u root -proot -e "DROP DATABASE IF EXISTS demo; CREATE DATABASE demo;"
	$(COMPOSE) exec -T -e DATABASE_URL="$(DATABASE_URL_MYSQL)" $(SERVICE_PHP) sh -c 'mkdir -p var && php -d memory_limit=256M scripts/ensure-database.php 2>/dev/null; php -d memory_limit=256M bin/console doctrine:migrations:migrate --no-interaction --write-sql=var/expected_migrations_mysql.sql'
	@echo "Reference SQL written to var/expected_migrations_mysql.sql (MySQL 8). Use it to check correctness (see docs/DEMO_MIGRATIONS_REFERENCE.md)."

db-reset:
	@rm -f var/data.db
	@echo "DB reset: var/data.db removed. Run 'make migrate' to apply migrations."

check-pdo-mysql:
	@$(COMPOSE) exec -T $(SERVICE_PHP) php -m 2>/dev/null | grep -q pdo_mysql || (echo "PDO MySQL driver not found. Rebuild the image: make build"; echo "Then: make up (or make db-reset-mysql / make migrate-mysql)"; exit 1)

up-mysql: ensure-up check-pdo-mysql
	$(COMPOSE) up -d mysql
	@echo "Waiting for MySQL 8 to be healthy (up to 60s)..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do \
		if $(COMPOSE) exec -T mysql mysqladmin ping -h localhost -u root -proot 2>/dev/null; then \
			echo "MySQL is ready."; break; \
		fi; \
		if [ $$i -eq 20 ]; then echo "MySQL did not become ready. Check: make logs"; exit 1; fi; \
		echo "  waiting... ($$i/20)"; sleep 3; \
	done
	@echo "Run 'make migrate-mysql' to apply migrations against MySQL 8."

db-reset-mysql: ensure-up check-pdo-mysql
	$(COMPOSE) up -d mysql
	@echo "Waiting for MySQL..."
	@for i in 1 2 3 4 5 6 7 8 9 10; do \
		if $(COMPOSE) exec -T mysql mysqladmin ping -h localhost -u root -proot 2>/dev/null; then break; fi; \
		sleep 2; \
	done
	$(COMPOSE) exec -T mysql mysql -u root -proot -e "DROP DATABASE IF EXISTS demo; CREATE DATABASE demo;"
	@echo "MySQL DB reset. Run 'make migrate-mysql' to apply migrations."

migrate-mysql: ensure-up check-pdo-mysql
	$(COMPOSE) up -d mysql
	@echo "Waiting for MySQL 8..."
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do \
		if $(COMPOSE) exec -T mysql mysqladmin ping -h localhost -u root -proot 2>/dev/null; then break; fi; \
		if [ $$i -eq 20 ]; then echo "MySQL not ready."; exit 1; fi; \
		sleep 3; \
	done
	$(COMPOSE) exec -T -e DATABASE_URL="$(DATABASE_URL_MYSQL)" $(SERVICE_PHP) sh -c 'mkdir -p var && php -d memory_limit=256M scripts/ensure-database.php 2>/dev/null; php -d memory_limit=256M bin/console doctrine:migrations:migrate --no-interaction -vvv'
	@echo "Migrations applied to MySQL 8."

test-mysql: update-bundle db-reset-mysql migrate-mysql
	@echo "MySQL test run complete."

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
