# Makefile for Migrations Kit Bundle
# Development and QA targets run inside the Docker container
#
COMPOSE_FILE := docker-compose.yml
# Prefer Compose V2 plugin; fall back to docker-compose V1 (REQ-MAKE-010).
# Use absolute docker path so Make does not resolve a local ./docker/ directory (EACCES).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
DOCKER_BIN := docker
endif
COMPOSE_BIN := $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
COMPOSE := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php
RUN := $(COMPOSE) exec -T $(SERVICE_PHP)

# For demo targets that run on the host (demo-up-*, demo-migrate-*)
COMPOSER ?= composer

.PHONY: help install test test-coverage coverage-php-percent cs-check cs-fix qa clean ensure-up update update-deps update-deps-demos validate assets release-check release-check-demos demo-smoke composer-sync rector rector-dry phpstan check-no-cursor-coauthor strip-cursor-coauthor-from-history setup-hooks
.PHONY: demo-up-symfony8 demo-migrate-symfony8
.PHONY: up down down-dev up-symfony8 build shell demo-install demo-down

# Default target
help:
	@echo "Migrations Kit Bundle - Development Commands (Docker)"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up             Start root container ($(COMPOSE) at bundle root)"
	@echo "  down           Stop root container"
	@echo "  down-dev       Stop root $(COMPOSE) (dev) and remove orphans"
	@echo "  build          Rebuild root Docker image (no cache)"
	@echo "  shell          Open shell in root container"
	@echo "  install        Install Composer dependencies"
	@echo "  assets         No frontend assets in this bundle (no-op)"
	@echo "  test           Run PHPUnit tests"
	@echo "  test-coverage  Run tests with code coverage (PCOV in container)"
	@echo "  cs-check       Check code style (PHP-CS-Fixer)"
	@echo "  cs-fix         Fix code style"
	@echo "  rector         Apply Rector refactoring"
	@echo "  rector-dry     Run Rector in dry-run mode"
	@echo "  phpstan        Run PHPStan static analysis"
	@echo "  qa             Run all QA (cs-check + test)"
	@echo "  release-check  Pre-release: git hygiene, cs-fix, cs-check, rector-dry, phpstan, test-coverage, demo healthchecks"
	@echo "  demo-smoke     REQ-TEST-011: boot demo + HTTP 200 (make -C demo demo-smoke)"
	@echo "  composer-sync  Validate composer.json and align composer.lock (no install)"
	@echo "  clean          Remove vendor, cache, coverage"
	@echo "  update         Update composer.lock (composer update)"
	@echo "  update-deps    Update composer in bundle container and all demos (REQ-MAKE-008)"
	@echo "  validate       Run composer validate --strict"
	@echo "  setup-hooks    Install .githooks (commit-msg strips Cursor co-author; REQ-GIT-001)"
	@echo "  check-no-cursor-coauthor  Fail if git history has Cursor co-author trailers"
	@echo ""
	@echo "Demos:"
	@echo "  demo-install   Install Composer dependencies in demo"
	@echo "  demo-up-symfony8   Install deps in demo/symfony8"
	@echo "  up-symfony8    Start demo symfony8 (http://localhost:8008)"
	@echo "  demo-migrate-symfony8  Run migrations in demo/symfony8"
	@echo "  demo-down      Stop demo containers"
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

# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: install
	$(COMPOSE) exec $(SERVICE_PHP) composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: install
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

cs-check: install
	$(RUN) composer cs-check

cs-fix: install
	$(RUN) composer cs-fix

rector: install
	$(RUN) composer rector

rector-dry: install
	$(RUN) composer rector-dry

phpstan: install
	$(RUN) composer phpstan

qa: install
	$(RUN) composer qa

release-check: check-no-cursor-coauthor ensure-up composer-sync cs-fix cs-check rector-dry phpstan test-coverage release-check-demos

release-check-demos:
	@$(MAKE) -C demo release-check

demo-smoke:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo demo-smoke; else echo "No demo/Makefile — skip demo-smoke"; fi

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
demo-up-symfony8:
	@echo "Installing demo symfony8..."
	cd demo/symfony8 && $(COMPOSER) install --no-interaction
	@echo "✅ demo/symfony8 ready"

demo-migrate-symfony8:
	cd demo/symfony8 && mkdir -p var && $(COMPOSER) migrate

# Root $(COMPOSE) (bundle dev: install, test, cs-check, etc.)
up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Installing dependencies..."
	@sleep 2
	$(RUN) composer install --no-interaction
	@echo "✅ Root container ready!"

down:
	$(COMPOSE) down

down-dev:
	$(COMPOSE) down --remove-orphans

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

build:
	$(COMPOSE) build --no-cache

# Demo con Docker (FrankenPHP)
up-symfony8:
	$(MAKE) -C demo/symfony8 up

demo-down:
	$(MAKE) -C demo/symfony8 down

demo-install:
	$(MAKE) -C demo/symfony8 install


setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh master
