# Contributing

Use PHP 8.3 or newer, Composer 2, and Node.js 22+. Git integration tests use temporary local
repositories with synthetic identities and never push.

```sh
composer install
npm ci --prefix docs
make fmt
make verify
```

`make verify` runs Composer validation, formatting checks, PHPStan, PHPUnit,
dependency audit, PHAR build, installed-package smoke tests, and the VitePress documentation build. A build requires
network access to Composer package downloads. `make test`, `make analyse`,
`make lint`, `make build`, and `make smoke` are available separately.

Keep command classes focused on CLI inputs and output. Configuration parsing,
version transitions, edit planning, filesystem updates, and Git operations live
in their corresponding namespaces. Add regression tests for changed behavior,
especially write failures and release boundaries. Tests must restore their working
directory if changed and remove their temporary files.

Record user-visible changes under `Unreleased` in `CHANGELOG.md`. Keep README
examples short and detailed behavior in `docs/`. Check documentation commands
against the CLI. Do not add local plans, progress logs, or verification reports
to documentation; local artifacts belong in ignored `.cache/`.

Use generic examples and synthetic fixtures. Do not commit credentials, personal
paths, private service URLs, copied project data, or machine-specific reports.
Inspect `dist/manifest.txt` when changing packaging. Public license attribution
must remain intact.

See [release maintenance](docs/releases.md) and [security reporting](SECURITY.md).

Run `make docs-dev` to preview the documentation and `make docs` to build it.
The stable VitePress dependency uses a Vite 6 override to include security fixes;
keep that override until the upstream stable release upgrades its Vite dependency.
