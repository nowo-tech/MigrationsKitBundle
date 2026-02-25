# Makefile for Migrations Kit Bundle
# Development and QA targets run inside the Docker container
#
COMPOSE_FILE := docker-compose.yml
COMPOSE := docker compose -f $(COMPOSE_FILE)
SERVICE_PHP := php
RUN := $(COMPOSE) exec -T $(SERVICE_PHP)

# For demo targets that run on the host (demo-up-*, demo-migrate-*)
COMPOSER ?= composer

.PHONY: help install test test-coverage cs-check cs-fix qa clean ensure-up update validate assets release-check release-check-demos composer-sync
.PHONY: demo-up-symfony7 demo-up-symfony8 demo-migrate-symfony7 demo-migrate-symfony8
.PHONY: up down up-symfony7 up-symfony8 build shell demo-install

# Default target
help:
	@echo "Migrations Kit Bundle - Development Commands (Docker)"
	@echo ""
	@echo "Usage: make <target>"
	@echo "  Development targets run inside the container."
	@echo ""
	@echo "Targets:"
	@echo "  install       Install Composer dependencies"
	@echo "  test          Run PHPUnit tests"
	@echo "  test-coverage Run tests with code coverage (PCOV in container)"
	@echo "  cs-check      Check code style (PHP-CS-Fixer)"
	@echo "  cs-fix        Fix code style"
	@echo "  qa            Run all QA (cs-check + test)"
	@echo "  release-check Pre-release: cs-fix, cs-check, test-coverage, demo healthchecks"
	@echo "  composer-sync Validate composer.json and align composer.lock (no install)"
	@echo "  clean         Remove vendor, cache, coverage"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  validate      Run composer validate --strict"
	@echo "  assets        No frontend assets in this bundle (no-op)"
	@echo ""
	@echo "Demos (run from bundle root; demos use path repo ../..):"
	@echo "  demo-up-symfony7   Install deps in demo/symfony7"
	@echo "  demo-up-symfony8   Install deps in demo/symfony8"
	@echo "  demo-migrate-symfony7  Run migrations in demo/symfony7"
	@echo "  demo-migrate-symfony8  Run migrations in demo/symfony8"
	@echo ""
	@echo "Demos with Docker (FrankenPHP):"
	@echo "  up             Start demo symfony8 (http://localhost:8008)"
	@echo "  down           Stop demo containers"
	@echo "  up-symfony7    Start demo symfony7 (http://localhost:8007)"
	@echo "  up-symfony8    Start demo symfony8 (http://localhost:8008)"
	@echo "  build          Rebuild Docker image (no cache)"
	@echo "  shell          Open shell in container"
	@echo "  demo-install   Install Composer dependencies"
	@echo ""

# Ensure the container is up; if not, start docker compose
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Container not running. Starting docker compose..."; \
		$(COMPOSE) up -d; \
		sleep 2; \
	fi

install: ensure-up
	$(RUN) composer install

test: install
	$(RUN) composer test

test-coverage: install
	$(RUN) composer test-coverage

cs-check: install
	$(RUN) composer cs-check

cs-fix: install
	$(RUN) composer cs-fix

qa: install
	$(RUN) composer qa

release-check: ensure-up composer-sync cs-fix cs-check test-coverage release-check-demos

release-check-demos:
	@$(MAKE) -C demo release-verify

composer-sync: ensure-up
	$(RUN) composer update --no-install
	$(RUN) composer validate --strict

clean: ensure-up
	$(RUN) sh -c 'rm -rf vendor .phpunit.cache coverage coverage.xml .php-cs-fixer.cache'

update: ensure-up
	$(RUN) composer update

validate: ensure-up
	$(RUN) composer validate --strict

# No frontend assets in this bundle
assets:
	@echo "No frontend assets in this bundle."

# Demo targets (install + migrate)
demo-up-symfony7:
	@echo "Installing demo symfony7..."
	cd demo/symfony7 && $(COMPOSER) install --no-interaction
	@echo "✅ demo/symfony7 ready"

demo-up-symfony8:
	@echo "Installing demo symfony8..."
	cd demo/symfony8 && $(COMPOSER) install --no-interaction
	@echo "✅ demo/symfony8 ready"

demo-migrate-symfony7:
	cd demo/symfony7 && mkdir -p var && $(COMPOSER) migrate

demo-migrate-symfony8:
	cd demo/symfony8 && mkdir -p var && $(COMPOSER) migrate

# Demo con Docker (FrankenPHP)
up: up-symfony8

down:
	$(MAKE) -C demo/symfony8 down

up-symfony7:
	$(MAKE) -C demo/symfony7 up

up-symfony8:
	$(MAKE) -C demo/symfony8 up

build:
	$(MAKE) -C demo/symfony8 build

shell:
	$(MAKE) -C demo/symfony8 shell

demo-install:
	$(MAKE) -C demo/symfony8 install
