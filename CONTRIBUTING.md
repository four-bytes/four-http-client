# Contributing

Thanks for your interest in improving `four-http-client`.

## Reporting issues

- Search the existing issues first to avoid duplicates.
- Open an issue for bugs and feature requests; describe the expected and actual behavior.
- For bugs, include the PHP version, the client version, and a minimal reproduction (ideally with a mocked PSR-18 client).
- Never paste real API keys or customer data into an issue.

## Pull requests

- Open an issue before starting significant work, so the approach can be agreed on first.
- Keep pull requests focused: one topic per PR, no unrelated refactoring.
- Add or update tests for behavior changes.
- Update `HISTORY.md` when the change is user-visible.
- Make sure all local quality gates pass before requesting a review (see below).

## Coding style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/).
- Every PHP file starts with `declare(strict_types=1);`.
- Use PSR-4 autoloading under `Four\Http\`.
- Keep the public API stable; treat breaking changes as a major version bump.
- Write code, comments, commit messages, and documentation in English.

## Running tests and static analysis

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

`phpunit` runs the test suite in `tests/`; `phpstan analyse` uses the project configuration (`phpstan.neon`). Both must be green before a pull request is merged.

## Commit messages

Use conventional commits, for example:

```
fix: handle empty 204 responses
feat: add per-request signing hook
docs: translate README to English
```

## License

By contributing, you agree that your contributions are licensed under the Apache-2.0 license.
