<p align="center">
  <img src="docs/public/favicon.svg" alt="Bumpversion" width="160">
</p>

<h1 align="center">Bumpversion</h1>

<p align="center">
  <strong>One version. Every file.</strong><br>
  PHP CLI · Semantic Versioning · Keep a Changelog · Git releases
</p>

<p align="center">
  <a href="https://github.com/janalis/bumpversion/actions/workflows/ci.yml"><img src="https://github.com/janalis/bumpversion/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://janalis.github.io/bumpversion/"><img src="https://github.com/janalis/bumpversion/actions/workflows/docs.yml/badge.svg?branch=master" alt="Docs"></a>
  <a href="https://github.com/janalis/bumpversion/releases"><img src="https://img.shields.io/github/v/release/janalis/bumpversion?sort=semver" alt="Latest release"></a>
  <a href="https://packagist.org/packages/janalis/bumpversion"><img src="https://img.shields.io/packagist/v/janalis/bumpversion" alt="Packagist"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/janalis/bumpversion" alt="MIT license"></a>
</p>

<p align="center">
  <a href="https://janalis.github.io/bumpversion/"><strong>Documentation</strong></a> ·
  <a href="https://janalis.github.io/bumpversion/getting-started">Getting started</a> ·
  <a href="https://janalis.github.io/bumpversion/configuration">CLI reference</a> ·
  <a href="https://janalis.github.io/bumpversion/changelogs">Changelogs</a>
</p>

---

Bumpversion keeps version strings in sync across your project, releases curated
Keep a Changelog entries, and optionally creates Git commits and tags. It is a
PHP tool inspired by [peritus/bumpversion](https://github.com/peritus/bumpversion).

## Install

Requires PHP 8.3 or newer. Git is needed only for Git release actions.

```sh
composer require --dev janalis/bumpversion
```

See [Installation](https://janalis.github.io/bumpversion/installation) for Composer,
standalone PHARs, and building from source. The documentation currently previews
the upcoming modernized release.

## Quick start

From a project containing a `VERSION` file with `1.0.0`:

```sh
vendor/bin/bumpversion init --no-interaction --current-version=1.0.0 --path=VERSION
vendor/bin/bumpversion bump --dry-run                    # preview changes
vendor/bin/bumpversion bump                              # 1.0.0 → 1.0.1
vendor/bin/bumpversion bump --type=minor                 # next minor version
vendor/bin/bumpversion bump --type=major --pre-release=alpha
vendor/bin/bumpversion bump --commit --tag --dry-run      # preview a Git release
```

Run `init` interactively to choose a version, changelog, and files or directories.
Git releases require a clean working tree and stage only planned release files.
Nothing is pushed.

## Highlights

- **Semantic Versioning 2.0.0**: major, minor, patch, prerelease counters, and
  explicit targets; correct precedence with arbitrary-length numeric components.
- **Keep a Changelog**: release curated Unreleased entries into dated sections,
  preserve history, and maintain comparison links.
- **Preview changes** with `--dry-run` before updating any files, commits, or tags.
- **Scoped file updates**: exact paths and complete version tokens, with validated
  edit plans and permission preservation.
- **Optional Git releases**: independent `--commit` and `--tag` controls, without
  staging unrelated files.

## Configuration

`init` creates `bumpversion.yaml`:

```yaml
bumpversion:
    version: 1.0.0
    paths:
        - VERSION
        - src
    changelog: null
```

Paths resolve relative to the configuration directory. Set `changelog` to an
existing Keep a Changelog file to release its Unreleased section alongside the
version update. Commit the configuration so contributors share the same process.

See the [configuration reference](https://janalis.github.io/bumpversion/configuration),
[Git release guide](https://janalis.github.io/bumpversion/releases), and
[migration notes](https://janalis.github.io/bumpversion/migration).

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) and the
[contributor guide](https://janalis.github.io/bumpversion/contributing).
Security reporting guidance is in [SECURITY.md](SECURITY.md).

```sh
composer install
npm ci --prefix docs
make fmt             # format PHP
make verify          # checks, tests, packaging, and docs
make docs-dev        # preview the documentation
```
