# Quick start

Bumpversion is a PHP command-line tool for keeping version strings in sync across
a project. It uses Semantic Versioning 2.0.0 and can release curated Keep a
Changelog entries alongside your code.

## Install

Use PHP 8.3 or newer:

```sh
composer require --dev janalis/bumpversion
```

## Configure the files to update

Create a `VERSION` file containing `1.0.0`, then initialize the configuration:

```sh
vendor/bin/bumpversion init --no-interaction --current-version=1.0.0 --path=VERSION
```

Add more `--path` options for additional files or directories. You can also run
`init` interactively. Paths resolve relative to the configuration file.

## Preview and bump

```sh
vendor/bin/bumpversion bump --dry-run
vendor/bin/bumpversion bump
```

The default increment changes `1.0.0` to `1.0.1`. Select a larger increment:

```sh
vendor/bin/bumpversion bump --type=minor
vendor/bin/bumpversion bump --type=major
```

## Prepare a Git release

Commit the project configuration and any other pending work first. Then:

```sh
vendor/bin/bumpversion bump --commit --tag --dry-run
vendor/bin/bumpversion bump --commit --tag
```

The commit includes only the planned release files. Publishing remains an explicit
step you perform yourself.

Next, add a [curated changelog](changelogs.md) or read the
[configuration and CLI reference](configuration.md).
