# Makefile to manage Migrations Kit Bundle demos
# From bundle root: make demo-up-symfony8 / make demo-migrate-symfony8
# From demo/: make up-symfony8 / make migrate-symfony8 (no Docker), or make docker-up-symfony8 (Docker)

.PHONY: help up-all migrate-all clean-all release-verify demo-smoke update-bundle-all
.PHONY: up-symfony8 migrate-symfony8
.PHONY: docker-up-symfony8 docker-down-symfony8
.PHONY: docker-restart-symfony8 docker-build-symfony8
.PHONY: docker-install-symfony8
.PHONY: docker-setup-symfony8 docker-update-bundle-symfony8

DEMOS := symfony8

help:
	@echo "Migrations Kit Bundle - Demos"
	@echo ""
	@echo "Demos: $(DEMOS)"
	@echo ""
	@echo "Without Docker (from bundle root: make demo-up-X / make demo-migrate-X):"
	@echo "  up-symfony8    Install in demo/symfony8"
	@echo "  migrate-symfony8  Run migrations in demo/symfony8"
	@echo ""
	@echo "With Docker (run from demo/ folder: cd symfony8 && make up && make setup):"
	@echo "  docker-up-symfony8   Start container demo/symfony8"
	@echo "  docker-down-symfony8  Stop container demo/symfony8"
	@echo "  docker-restart-symfony8  Restart container (demo/symfony8)"
	@echo "  docker-build-symfony8    Rebuild image no cache (demo/symfony8)"
	@echo "  docker-install-symfony8  composer install in container (demo/symfony8)"
	@echo "  docker-setup-symfony8 install + migrate in container (demo/symfony8)"
	@echo "  docker-update-bundle-symfony8  Update bundle from path repo (demo/symfony8)"
	@echo ""
	@echo "  up-all         Install dependencies in all demos (no Docker)"
	@echo "  migrate-all    Run migrations in all demos (no Docker)"
	@echo "  clean-all      Remove vendor/ and var/ in all demos"
	@echo "  demo-smoke     REQ-TEST-011: up -> HTTP 200 -> down"
	@echo "  release-verify Alias of demo-smoke"
	@echo ""

up-all:
	@for d in $(DEMOS); do $(MAKE) -C .. demo-up-$$d; done

migrate-all:
	@for d in $(DEMOS); do $(MAKE) -C .. demo-migrate-$$d; done

clean-all:
	@for d in $(DEMOS); do rm -rf $$d/vendor $$d/var; echo "Cleaned $$d"; done

test-coverage-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d test-coverage || exit 1; done
	@echo "test-coverage-all done."
release-check: update-bundle-all test-coverage-all release-verify
	@echo "All demos passed release-check."

update-bundle-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d update-bundle || exit 1; done
	@echo "All demos updated with latest bundle code."

release-verify: demo-smoke

# REQ-TEST-011: boot demo stack and expect HTTP 200
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8008; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT)"; \
	$(MAKE) docker-up-symfony8; sleep 12; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" 2>/dev/null || echo "000"); \
	$(MAKE) docker-down-symfony8 2>/dev/null || true; \
	if [ "$$code" != "200" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

up-symfony8:
	$(MAKE) -C .. demo-up-symfony8

migrate-symfony8:
	$(MAKE) -C .. demo-migrate-symfony8

# Docker: start container (from demo/ dir, so we are in demo/)
docker-up-symfony8:
	$(MAKE) -C symfony8 up

docker-down-symfony8:
	$(MAKE) -C symfony8 down

docker-restart-symfony8:
	$(MAKE) -C symfony8 restart

docker-build-symfony8:
	$(MAKE) -C symfony8 build

docker-install-symfony8:
	$(MAKE) -C symfony8 install

docker-setup-symfony8:
	$(MAKE) -C symfony8 setup

docker-update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle

# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
