# Configuration and CLI

## YAML configuration

The default file is `bumpversion.yaml`. If it is absent, `bumpversion.yaml.dist`
is used and updated directly. An explicit `--configuration` follows the same
fallback. A missing configuration produces an error and directs you to `init`.

```yaml
bumpversion:
    version: 1.2.3
    paths:
        - VERSION
        - src
    changelog: CHANGELOG.md
```

- `version` is a required three-part semantic version string. Quote values where
  needed to prevent YAML scalar conversion. Prerelease and build metadata are supported.
- `paths` is a nonempty list of existing text files or directories. It defaults
  to `['.']` for existing configurations that omit it.
- `changelog` is an optional existing file. Its curated Unreleased entries are moved into a dated release section
  whenever the version is bumped, with or without Git.

Paths resolve from the configuration directory and must remain inside it.
Symlink paths are rejected; symlinks encountered during directory traversal are
skipped. Directory traversal excludes `.git`, `.hg`, `.svn`, `vendor`,
`node_modules`, `.cache`, `.phpunit.cache`, and `dist`. Binary files are skipped
when scanning directories and rejected when explicitly configured.

Only complete version tokens are replaced. For example, bumping `1.2.3` does not
change `11.2.3`, `1.2.30`, or `1.2.3-rc.1`. A version attached directly to a letter,
as in `v1.2.3`, is not a standalone token. File selection uses exact paths and
actual directory descendants, and overlapping selections are updated once.
Explicit files must contain the current version. Directories report match counts
and may contain no matching versions. The configuration and configured changelog are updated separately.

Configuration is serialized as YAML when updated, so comments and formatting are
not preserved. Additional YAML keys are retained. File permissions are preserved.

## Commands

```sh
bumpversion init [--configuration=FILE] [--current-version=VERSION] [--path=PATH] [--changelog=FILE]
bumpversion bump [--configuration=FILE] [--type=PART] [--pre-release=NAME]
bumpversion bump --new-version=VERSION [--dry-run] [--commit] [--tag]
```

`--path` can be repeated. Noninteractive initialization requires
`--no-interaction`, `--current-version`, and at least one `--path`.
Existing configuration is never overwritten by `init`.

`--configuration` also accepts `-c`, `--type` accepts `-t`, and `--pre-release`
accepts `-p`. `~/` configuration paths expand to the current home directory.
`--date=YYYY-MM-DD` selects the changelog release date (default: today in UTC).
`--tag-prefix=v` selects a Git tag prefix; otherwise it is inferred from an existing
Unreleased comparison link, falling back to an empty prefix.
Git options and `--dry-run` work with either form of `bump`.

### Version transitions

| Current | Options | Next |
| --- | --- | --- |
| `1.2.3` | none | `1.2.4` |
| `1.2.3` | `--type=minor` | `1.3.0` |
| `1.2.3` | `--type=major --pre-release=alpha` | `2.0.0-alpha.1` |
| `2.0.0-alpha.1` | `--pre-release=alpha` | `2.0.0-alpha.2` |
| `2.0.0-alpha.2` | `--pre-release=beta` | `2.0.0-beta.1` |
| `2.0.0-beta.1` | none | `2.0.0` |

An explicit type always increments that component and resets lower components.
Starting a prerelease from a stable version first increments the patch component.
A named prerelease starts at `.1`; a matching name increments its numeric suffix.
Prerelease names start with a letter or hyphen and contain letters, digits, or
hyphens. Build metadata is preserved when parsing and using `--new-version`, and
removed when calculating an increment. Numeric components and identifiers are processed as decimal strings without machine-integer limits.

`--new-version` accepts an explicit SemVer target with higher precedence than the
current version. Downgrades and metadata-only changes are rejected. It cannot be combined with `--type`
or `--pre-release`.

## Preview and errors

`--dry-run` performs validation and displays the transition, match counts, changed
paths, and Git actions. It writes no files, commits, or tags.

A successful command exits with `0`. Invalid inputs, missing files, failed writes,
and Git failures exit nonzero with a diagnostic. Use the exit status in CI;
do not parse human-readable output as a stable machine interface.
