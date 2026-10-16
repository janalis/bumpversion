# Changelog

Notable changes are recorded using [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Version numbers follow [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Require PHP 8.3+, Symfony 7.4, and current development tooling.
- Move internal classes from `App\` to `Janalis\Bumpversion\`.
- Validate complete semantic versions, fix prerelease increments, and support build metadata.
- Resolve configured paths from the configuration directory and update exact version tokens.
- Validate edits before writing, preserve permissions, and restore earlier writes on ordinary failures.
- Stage only files changed by the release and compare versions using SemVer precedence.
- Release curated Keep a Changelog entries with dates, comparison links, and preserved history.
- Build production PHARs in isolation with an explicit file allowlist.
- Publish VitePress documentation to GitHub Pages with local search and contribution guidance.

### Added

- Dry-run previews, explicit target versions, independent commit/tag controls, and noninteractive initialization.
- PHP 8.3–8.5 CI, dependency checks, regression tests, and installed-package smoke tests.
- Automated release preparation, versioned PHARs, curated GitHub release notes, and SHA-256 checksums.
- Packagist publication through the GitHub webhook, with verification of the exact indexed commit.

### Fixed

- Installation with modern PHP and Composer.
- CLI launchers for installed packages, custom binary directories, and unrelated working directories.
- False file-prefix matches, replacements inside larger versions, and unsafe symlink traversal.
- Untracked-file checks, duplicate-tag preflight, and semantic-version precedence.

[Unreleased]: https://github.com/janalis/bumpversion/compare/0c7d1d1...HEAD
