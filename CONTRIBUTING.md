# Contributing to Cookd Search

Thank you for taking the time to contribute.

## Getting Started

1. Fork the repository and create your branch from `main`.
2. Follow the [Getting Started](README.md#getting-started) instructions to set up a local dev environment.
3. Make your changes and write or update tests where appropriate.
4. Ensure linting and tests pass before opening a PR.

## Development Workflow

```bash
# Start the dev server (PHP + Vite + queue worker in parallel)
composer dev

# Run tests
composer test

# Fix code style
composer lint
```

## Pull Request Guidelines

- Keep PRs focused — one feature or fix per PR.
- Write a clear description explaining *what* changed and *why*.
- Include relevant test coverage for new behaviour.
- Reference any related issues in the PR description.

## Code Style

This project follows [Laravel Pint](https://laravel.com/docs/pint) (PSR-12 based). Run `composer lint` before committing. CI will fail on style violations.

## Reporting Issues

Open a [GitHub Issue](../../issues) with:
- A clear, descriptive title
- Steps to reproduce
- Expected vs actual behaviour
- PHP, PostgreSQL, and pgvector version information

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
