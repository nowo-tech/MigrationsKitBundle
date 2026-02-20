# Contributing

Thank you for considering contributing to Migrations Kit Bundle.

## Development setup

1. Clone the repository and install dependencies:
   ```bash
   composer install
   ```

2. Run tests:
   ```bash
   composer test
   # or with Docker: docker compose up -d --build && docker compose exec php composer test
   ```

3. Code style:
   ```bash
   composer cs-check
   composer cs-fix
   ```

4. Full QA:
   ```bash
   composer qa
   # or: make qa
   ```

## Code style

The project uses [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) with the rules in `.php-cs-fixer.dist.php`. Please run `composer cs-fix` before submitting a pull request.

## Tests

- Add or update unit tests as needed.
- Run `composer test` (or `make test`) and ensure all tests pass.
- Optionally run demos: `make demo-up-symfony8` and `make demo-migrate-symfony8`.

## Pull requests

- Open an issue first to discuss larger changes.
- Branch from `main` (or the default branch), make your changes, and open a PR.
- Keep the scope focused; split unrelated changes into separate PRs when possible.
- Update the documentation under `docs/` if you change configuration or behavior.

## Reporting issues

- Use the GitHub issue tracker.
- Include PHP, Symfony, Doctrine DBAL, and doctrine/migrations versions.
- Provide a minimal example or steps to reproduce when reporting bugs.

Thank you for contributing.
