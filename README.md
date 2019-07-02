# Bumpversion

A small command line tool aiming to simplify releasing process by updating all version strings in your source code by the correct increment.

## Installation

Require package with composer:
```bash
$ composer require --dev janalis/bumpversion
```

Create a configuration file:
```bash
$ bin/bumpversion init
```

This creates a configuration file `bumpversion.yaml`

## Use

### Bump version:
```bash
$ bin/bumpversion bump --type=patch
```
type must be one of `patch`, `minor` or `major`

This increments patch number of version.

### Bump version with a pre-release identifier:
```bash
$ bin/bumpversion bump --type=major --pre-release=alpha
```

This increments major number of version and adds a `-alpha.1` suffix to version.

### Bump version with a custom configuration file:
```bash
$ bin/bumpversion bump --configuration=~/bumpversion.yaml
```

This reads configuration from `~/bumversion.yaml`.

## Alternatives

* [peritus/bumpversion](https://pypi.org/project/bumpversion/) Original program that inspired this project
* [quazardous/php-bump-version](https://github.com/quazardous/php-bump-version) An other php version bumper

## Contribute

This project uses [symfony coding standard](https://symfony.com/doc/current/contributing/code/standards.html).

Contributions are welcomed!
