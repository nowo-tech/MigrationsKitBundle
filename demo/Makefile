# Makefile to manage Migrations Kit Bundle demos
# From bundle root: make demo-up-symfony8 / make demo-migrate-symfony8
# From demo/: make up-symfony8 / make migrate-symfony8 (no Docker), or make docker-up-symfony8 (Docker)

.PHONY: help up-all migrate-all clean-all
.PHONY: up-symfony6 up-symfony7 up-symfony8 migrate-symfony6 migrate-symfony7 migrate-symfony8
.PHONY: docker-up-symfony6 docker-up-symfony7 docker-up-symfony8 docker-down-symfony6 docker-down-symfony7 docker-down-symfony8
.PHONY: docker-setup-symfony6 docker-setup-symfony7 docker-setup-symfony8

DEMOS := symfony6 symfony7 symfony8

help:
	@echo "Migrations Kit Bundle - Demos"
	@echo ""
	@echo "Demos: $(DEMOS)"
	@echo ""
	@echo "Without Docker (from bundle root: make demo-up-X / make demo-migrate-X):"
	@echo "  up-symfony6    Install in demo/symfony6"
	@echo "  up-symfony7    Install in demo/symfony7"
	@echo "  up-symfony8    Install in demo/symfony8"
	@echo "  migrate-symfony6  Run migrations in demo/symfony6"
	@echo "  migrate-symfony7  Run migrations in demo/symfony7"
	@echo "  migrate-symfony8  Run migrations in demo/symfony8"
	@echo ""
	@echo "With Docker (run from demo/ folder: cd symfony8 && make up && make setup):"
	@echo "  docker-up-symfony6   Start container demo/symfony6"
	@echo "  docker-up-symfony7   Start container demo/symfony7"
	@echo "  docker-up-symfony8   Start container demo/symfony8"
	@echo "  docker-down-symfony6  Stop container demo/symfony6"
	@echo "  docker-setup-symfony6 install + migrate in container (demo/symfony6)"
	@echo "  docker-setup-symfony7 install + migrate in container (demo/symfony7)"
	@echo "  docker-setup-symfony8 install + migrate in container (demo/symfony8)"
	@echo ""
	@echo "  up-all         Install dependencies in all demos (no Docker)"
	@echo "  migrate-all    Run migrations in all demos (no Docker)"
	@echo "  clean-all      Remove vendor/ and var/ in all demos"
	@echo ""

up-all:
	@for d in $(DEMOS); do $(MAKE) -C .. demo-up-$$d; done

migrate-all:
	@for d in $(DEMOS); do $(MAKE) -C .. demo-migrate-$$d; done

clean-all:
	@for d in $(DEMOS); do rm -rf $$d/vendor $$d/var; echo "Cleaned $$d"; done

up-symfony6:
	$(MAKE) -C .. demo-up-symfony6

up-symfony7:
	$(MAKE) -C .. demo-up-symfony7

up-symfony8:
	$(MAKE) -C .. demo-up-symfony8

migrate-symfony6:
	$(MAKE) -C .. demo-migrate-symfony6

migrate-symfony7:
	$(MAKE) -C .. demo-migrate-symfony7

migrate-symfony8:
	$(MAKE) -C .. demo-migrate-symfony8

# Docker: start container (from demo/ dir, so we are in demo/)
docker-up-symfony6:
	$(MAKE) -C symfony6 up

docker-up-symfony7:
	$(MAKE) -C symfony7 up

docker-up-symfony8:
	$(MAKE) -C symfony8 up

docker-down-symfony6:
	$(MAKE) -C symfony6 down

docker-down-symfony7:
	$(MAKE) -C symfony7 down

docker-down-symfony8:
	$(MAKE) -C symfony8 down

docker-setup-symfony6:
	$(MAKE) -C symfony6 setup

docker-setup-symfony7:
	$(MAKE) -C symfony7 setup

docker-setup-symfony8:
	$(MAKE) -C symfony8 setup
