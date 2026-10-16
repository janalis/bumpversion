# Contributing

Install PHP 8.3+, Composer 2, and Node.js 22+.

```sh
composer install
npm ci --prefix docs
make fmt
make verify
```

Verification covers formatting, PHPStan, PHPUnit, dependency audit, PHAR packaging,
Composer consumer installation, and the VitePress documentation build. Git tests
use isolated local repositories and synthetic identities.

Keep command classes focused on inputs and output. Domain behavior lives in
version, configuration, changelog, edit-plan, and Git services. Add regression
tests for changed behavior and record user-visible changes under Unreleased.

## Documentation

```sh
make docs-dev
make docs
```

Sources live in `docs/`. VitePress checks internal links during the build and
provides local search without an external search service. The documentation
workflow validates pull requests and deploys the default branch to GitHub Pages.

Keep examples generic and fixtures synthetic. Do not include credentials,
personal paths, private URLs, copied project data, or machine-specific reports.
Local plans and verification artifacts belong in ignored `.cache/`.

See the [security policy](security.md) and [release maintenance](releases.md).
