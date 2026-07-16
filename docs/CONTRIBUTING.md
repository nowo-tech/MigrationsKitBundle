# Contributing

Thank you for considering contributing to Migrations Kit Bundle.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Table of contents

- [Development setup](#development-setup)
- [Code style](#code-style)
- [Tests](#tests)
- [Pull requests](#pull-requests)
- [Reporting issues](#reporting-issues)

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

5. Update dependencies (bundle + demos):
   ```bash
   make update-deps
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

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
