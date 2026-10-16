# Keep a Changelog and Semantic Versioning

Bumpversion follows [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/)
and [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## Curate the next release

Write release notes as you work, grouping them under Added, Changed, Deprecated,
Removed, Fixed, or Security. Keep the Unreleased section above past releases:

```markdown
# Changelog

## [Unreleased]

### Added

- Export reports as CSV.

### Fixed

- Keep column headings visible while scrolling.

## [1.0.0] - 2026-01-15

### Added

- First stable release.

[Unreleased]: https://example.com/project/compare/v1.0.0...HEAD
[1.0.0]: https://example.com/project/releases/tag/v1.0.0
```

Configure `changelog: CHANGELOG.md` in `bumpversion.yaml`. Then:

```sh
bumpversion bump --type=minor --date=2026-02-01 --dry-run
bumpversion bump --type=minor --date=2026-02-01
```

The notes move into `## [1.1.0] - 2026-02-01`, below a fresh Unreleased section.
The six categories remain intact, history stays in place, and existing comparison
links advance to the new tag. No Git release option is required.

Release dates default to the current UTC date. Use `--date` for repeatable builds
or a deliberate publication date. Existing target releases, duplicate Unreleased
headings, invalid dates, and malformed categories fail before files are written.
Yanked release markers in older sections remain untouched.

Comparison links are maintained when an existing Unreleased reference uses a
`/compare/OLD...HEAD` URL. Its tag prefix is reused, including for Git tags; use
`--tag-prefix` to override it. Other link formats and unrelated references are
preserved. Bumpversion never discovers a repository URL from Git remotes, so a
private remote cannot be silently copied into public notes. Without existing
comparison references, no repository URL is invented.

Commit logs are not used to generate changelog entries. Release notes remain your
curated account of changes that matter to users.

## Semantic versions

All complete SemVer 2.0.0 version forms are accepted, including numeric prerelease
identifiers, multiple identifiers, hyphens, and build metadata:

```text
0.0.0
1.0.0-alpha.1
1.0.0-0.3.7
1.0.0-x-y-z.--
1.0.0+20130313144700
1.0.0-beta+exp.sha.5114f85
```

The parser rejects leading zeroes in core and numeric prerelease components,
empty identifiers, underscores, non-ASCII identifiers, and abbreviated versions.
Numbers are compared and incremented as decimal strings without integer overflow.

Precedence follows SemVer's numeric and ASCII rules. A prerelease sorts below its
stable release; numeric identifiers sort below nonnumeric identifiers. Build
metadata does not participate in precedence. Targets must have higher precedence
than the stored version, so downgrades and build-metadata-only changes are rejected.

Choose `major` for incompatible public API changes, `minor` for compatible features
and deprecations, and `patch` for compatible fixes. Lower components reset on a
major or minor increment. The CLI does not infer the compatibility of your API.

`--pre-release=alpha` manages a named counter such as `alpha.1`. Starting a
prerelease from stable increments the patch first. Use `--new-version` to choose
any other valid SemVer identifier sequence explicitly.
