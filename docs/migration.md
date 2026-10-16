# Migration from the original PHP implementation

- PHP 8.3 or newer is required; Symfony components use the 7.4 series.
- Composer consumers should use `vendor/bin/bumpversion` or their configured binary
  directory. Source checkouts can continue using `bin/bumpversion`.
- YAML filenames, the `bumpversion` mapping, existing option names, and `init`/`bump`
  commands remain supported. A `.dist` fallback is updated directly, as before.
- Versions must be complete SemVer strings such as `1.2.0`. Abbreviated versions,
  underscores, and invalid numeric prerelease identifiers are rejected.
- Named prereleases consistently start at `.1`, including when used with `--type`.
  Starting one from a stable release increments the patch first.
- Targets must increase SemVer precedence; build metadata never changes precedence.
- Relative paths now resolve from the configuration directory, rather than the
  process's working directory. Paths outside that directory and symlinks are rejected.
- File and directory names match exactly. Version substrings inside larger tokens
  are no longer replaced. Explicit files with no version match fail before updates.
- Missing configuration now produces a clear error; run `init` explicitly.
- Git releases reject untracked files and stage only planned edits. Existing
  `--version-control-system=git` still enables a commit and tag; new `--commit`
  and `--tag` options allow independent control.
- Changelogs now use curated Keep a Changelog sections; commit logs are no longer
  imported. Add a top-level `## [Unreleased]` section before using an older changelog.
  Changelog releases run even without Git actions.
- Internal PHP classes now use `Janalis\Bumpversion\`; the former `App\` classes
  are not a supported compatibility API.
- PHAR output now lives in `dist/`. `create-phar.php` forwards to the isolated
  builder and requires `php -d phar.readonly=0 create-phar.php`.

This is a PHP tool inspired by Python bumpversion, not a drop-in replacement for
its INI configuration, customizable version formats, or Python command syntax.
