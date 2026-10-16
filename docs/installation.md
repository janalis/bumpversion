# Installation

## Composer

PHP 8.3–8.5 is tested in CI. Required PHP extensions are checked by Composer.

```sh
composer require --dev janalis/bumpversion
vendor/bin/bumpversion --help
```

Composer's configured binary directory is supported. For a global installation:

```sh
composer global require janalis/bumpversion
```

Add your global Composer binary directory to PATH. Commands operate on configuration
in the working directory unless `--configuration` points elsewhere.

## Standalone PHAR

Download `bumpversion.phar` and `SHA256SUMS` from a
[GitHub release](https://github.com/janalis/bumpversion/releases). Verify the download:

```sh
sha256sum -c SHA256SUMS
php bumpversion.phar --help
```

On macOS, use `shasum -a 256 -c SHA256SUMS`. On Unix, the PHAR can also be made
executable with `chmod +x bumpversion.phar` and placed on PATH. PHP is still required.

## From source

```sh
composer install
php bin/bumpversion --help
make build
php dist/bumpversion.phar --help
```

Building requires the Phar extension, Composer on PATH, and access to Composer
package downloads. The build enables `phar.readonly=0` for that process only and
installs production dependencies in an isolated temporary directory. It does not
remove development dependencies from your checkout.
