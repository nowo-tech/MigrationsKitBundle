# Makefile to manage Migrations Kit Bundle demos
# From bundle root: make demo-up-symfony8 / make demo-migrate-symfony8
# From demo/: make up-symfony8 / make migrate-symfony8 (no Docker), or make docker-up-symfony8 (Docker)

.PHONY: help up-all migrate-all clean-all release-verify
.PHONY: up-symfony7 up-symfony8 migrate-symfony7 migrate-symfony8
.PHONY: docker-up-symfony7 docker-up-symfony8 docker-down-symfony7 docker-down-symfony8
.PHONY: docker-restart-symfony7 docker-restart-symfony8 docker-build-symfony7 docker-build-symfony8
.PHONY: docker-install-symfony7 docker-install-symfony8
.PHONY: docker-setup-symfony7 docker-setup-symfony8 docker-update-bundle-symfony7 docker-update-bundle-symfony8

DEMOS := symfony7 symfony8

help:
	@echo "Migrations Kit Bundle - Demos"
	@echo ""
	@echo "Demos: $(DEMOS)"
	@echo ""
	@echo "Without Docker (from bundle root: make demo-up-X / make demo-migrate-X):"
	@echo "  up-symfony7    Install in demo/symfony7"
	@echo "  up-symfony8    Install in demo/symfony8"
	@echo "  migrate-symfony7  Run migrations in demo/symfony7"
	@echo "  migrate-symfony8  Run migrations in demo/symfony8"
	@echo ""
	@echo "With Docker (run from demo/ folder: cd symfony8 && make up && make setup):"
	@echo "  docker-up-symfony7   Start container demo/symfony7"
	@echo "  docker-up-symfony8   Start container demo/symfony8"
	@echo "  docker-down-symfony7  Stop container demo/symfony7"
	@echo "  docker-restart-symfony7  Restart container (demo/symfony7)"
	@echo "  docker-restart-symfony8  Restart container (demo/symfony8)"
	@echo "  docker-build-symfony7    Rebuild image no cache (demo/symfony7)"
	@echo "  docker-build-symfony8    Rebuild image no cache (demo/symfony8)"
	@echo "  docker-install-symfony7  composer install in container (demo/symfony7)"
	@echo "  docker-install-symfony8  composer install in container (demo/symfony8)"
	@echo "  docker-setup-symfony7 install + migrate in container (demo/symfony7)"
	@echo "  docker-setup-symfony8 install + migrate in container (demo/symfony8)"
	@echo "  docker-update-bundle-symfony7  Update bundle from path repo (demo/symfony7)"
	@echo "  docker-update-bundle-symfony8  Update bundle from path repo (demo/symfony8)"
	@echo ""
	@echo "  up-all         Install dependencies in all demos (no Docker)"
	@echo "  migrate-all    Run migrations in all demos (no Docker)"
	@echo "  clean-all      Remove vendor/ and var/ in all demos"
	@echo "  release-verify Start each demo, healthcheck (HTTP 200), then stop (for release-check)"
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
release-check: test-coverage-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for d in $(DEMOS); do \
		port=$$(grep '^PORT=' $$d/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
		[ -z "$$port" ] && port=$$(case $$d in symfony7) echo 8007;; symfony8) echo 8008;; *) echo 8000;; esac); \
		echo "🔍 Release verify: $$d (port $$port)..."; \
		$(MAKE) docker-up-$$d; \
		echo "⏳ Waiting for $$d to be ready..."; sleep 15; \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) docker-down-$$d 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "❌ $$d healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "✅ $$d OK (HTTP $$code)"; \
	done
	@echo "✅ All demos passed release-verify."

up-symfony7:
	$(MAKE) -C .. demo-up-symfony7

up-symfony8:
	$(MAKE) -C .. demo-up-symfony8

migrate-symfony7:
	$(MAKE) -C .. demo-migrate-symfony7

migrate-symfony8:
	$(MAKE) -C .. demo-migrate-symfony8

# Docker: start container (from demo/ dir, so we are in demo/)
docker-up-symfony7:
	$(MAKE) -C symfony7 up

docker-up-symfony8:
	$(MAKE) -C symfony8 up

docker-down-symfony7:
	$(MAKE) -C symfony7 down

docker-down-symfony8:
	$(MAKE) -C symfony8 down

docker-restart-symfony7:
	$(MAKE) -C symfony7 restart

docker-restart-symfony8:
	$(MAKE) -C symfony8 restart

docker-build-symfony7:
	$(MAKE) -C symfony7 build

docker-build-symfony8:
	$(MAKE) -C symfony8 build

docker-install-symfony7:
	$(MAKE) -C symfony7 install

docker-install-symfony8:
	$(MAKE) -C symfony8 install

docker-setup-symfony7:
	$(MAKE) -C symfony7 setup

docker-setup-symfony8:
	$(MAKE) -C symfony8 setup

docker-update-bundle-symfony7:
	$(MAKE) -C symfony7 update-bundle

docker-update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle
