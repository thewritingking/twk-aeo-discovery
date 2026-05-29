# Contributing to TWK AEO Discovery

This is a solo-maintained project but issues and pull requests are welcome.

## Reporting issues

Open a [GitHub Issue](../../issues) with:

- WordPress version
- PHP version
- Plugin version
- Which SEO plugin is active (if any)
- A clear description of what you expected vs what happened
- For schema-output issues: paste the `<script type="application/ld+json">` block from view-source so the actual emitted JSON is visible

## Pull requests

Open an issue first to discuss substantive changes. For small fixes (typos, doc improvements, obvious bugs) feel free to send a PR directly.

Code standards:

- PHP 7.4 floor — no PHP-8-only syntax
- WordPress coding standards (tab indentation, snake_case, escape on output, sanitize on input)
- Add a `phpcs:ignore` comment with a one-line reason if you have to deviate
- Run `php -l` on every file you change

## Security issues

Do not report security issues via public GitHub Issues. Email Richard at the address on [thewritingking.com](https://thewritingking.com) with the details.

## License

By contributing you agree your contributions are licensed under the GPLv2-or-later license that covers the rest of the project.
