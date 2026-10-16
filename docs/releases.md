# Releases

## Releasing a consuming project

Without Git options, `bump` updates files and configuration only. Choose Git
operations explicitly:

```sh
bumpversion bump --commit                  # update files and create a commit
bumpversion bump --commit --tag            # also tag that commit
bumpversion bump --tag                     # tag existing HEAD; leave file edits uncommitted
bumpversion bump --version-control-system=git  # legacy alias for both
```

Release tags are lightweight. By default, tags use the version alone; an existing
Unreleased comparison link supplies its prefix, or `--tag-prefix=v` overrides it.
Git operations require an existing HEAD and a clean index and working tree,
including untracked files. Duplicate target tags fail before files are changed.
Ignored unrelated files are not staged. Ignored release files are rejected before
writes. Git author identity and hooks follow the repository's Git configuration.
Bumpversion never pushes to a remote.

If a changelog is configured, its curated Unreleased entries become the next dated
release section. All existing release history and yanked markers are retained.
The changelog is updated even without Git operations. No commit logs, author
names, email addresses, or remote URLs are imported. See
[Keep a Changelog and SemVer](changelogs.md).

Files are planned and validated together, then replaced individually using temporary
files in their directories. Ordinary write failures restore earlier writes when
possible. This is not a cross-file atomic transaction or a crash recovery mechanism.
Avoid concurrent release operations.

If a Git command fails after file updates, inspect the working tree and index.
If a release commit succeeded but tagging failed, the diagnostic says so; the
commit is retained. Correct the problem and complete the Git operation manually.
Do not rerun `bump` blindly, as the stored version has already changed.

## Built-in release commands

Release preparation and publishing are available in every Composer or PHAR
installation. No `gh`, `curl`, or repository helper scripts are required.

```sh
bumpversion release:prepare minor --dry-run --format=json
bumpversion release:prepare 1.1.0 --tag-prefix=v
bumpversion release:notes                    # current dated section
bumpversion release:notes 1.0.0 --format=json # historical section
```

Preparation requires a clean Git checkout and rejects an existing target tag
before changing files. It uses the configured paths and changelog, accepts
`--date`, and never commits, tags, or pushes. Output formats are `text`, `json`,
and `github` (single-line outputs for `$GITHUB_OUTPUT`). Exact targets must have
higher SemVer precedence. Notes preserve fenced examples and reference links;
Markdown output normalizes line endings to LF.

After reviewing, committing, tagging, and pushing the prepared release, publish:

```sh
bumpversion release:github --repository=example/package --tag-prefix=v \
  --commit=FULL_40_CHARACTER_COMMIT_SHA --asset=dist/app.phar --asset=dist/SHA256SUMS
bumpversion release:packagist --package=example/package \
  --commit=FULL_40_CHARACTER_COMMIT_SHA
```

Both commands accept `--configuration`, `--release-version`, `--http-client`, and
`--dry-run`. The expected commit defaults to local HEAD. GitHub requires the
version to match the prepared configuration and verifies the remote tag points
to the expected commit, including annotated tags. Assets are explicit, repeatable
`--asset` paths, relative to the configuration directory. `--notes-file` overrides
changelog extraction. Set `GH_TOKEN` (or `GITHUB_TOKEN`) in the environment with
repository contents write permission. Release titles are exactly the tag.

New GitHub releases remain drafts until all selected assets pass size and SHA-256
checks. Matching drafts can resume after failure. Published assets are never
replaced; reruns verify their contents. Published notes stay unchanged. An old
release display title can be corrected to its tag. Inspect conflicting draft
metadata or asset checksums before retrying.

Packagist uses webhook indexing by default. Set both `PACKAGIST_USERNAME` and
`PACKAGIST_TOKEN` to request a refresh through its API before verification.
The package defaults to `publishing.packagist_package`, then the consuming
project's `composer.json` name. It polls for the exact version and source commit,
failing immediately on a commit mismatch. `--timeout=300` controls the polling
window (0–3600 seconds); an in-flight HTTP request may finish after this window.
A delayed index can be verified again without republishing GitHub assets.

### Publishing configuration and HTTP clients

Optional defaults belong under `bumpversion.publishing`; flags override them:

```yaml
bumpversion:
    version: 1.0.0
    paths: [VERSION]
    changelog: CHANGELOG.md
    publishing:
        github_repository: example/package
        packagist_package: example/package
        http_client: auto
```

Credentials belong only in environment variables. HTTP publishing uses PSR-18
and PSR-17 with HTTPlug discovery; its Composer plugin is optional and disabled
in this package. Symfony HttpClient and Nyholm PSR-7 are bundled defaults,
including in the PHAR. `auto` prefers an installed Guzzle client when the consuming
project directly requires `guzzlehttp/guzzle` without directly requiring
`symfony/http-client`; otherwise it uses Symfony or a custom discovered PSR-18
implementation. `--http-client=symfony` and `--http-client=guzzle` select explicitly.
Guzzle must be installed by the consuming project to select it.

The internal publishing services also accept an injected PSR-18 client, and
`ApiClient` accepts PSR-17 request and stream factories. Native clients verify TLS,
disable automatic redirects, and use bounded request timeouts. GET failures and
rate limits receive at most two retries; mutations are never automatically retried.
Asset uploads and checksum downloads stream their bodies. Signed downloads
receive no API authorization header, and transport diagnostics omit credentials
and response bodies. Custom injected or discovered clients must provide their own
transport timeout and TLS policy.

## Releasing this package

Use **Actions → Release → Run workflow** on `master`. Set `version` to an exact
SemVer value or `major`, `minor`, or `patch`. Leave `dry_run` enabled to prepare
and verify the release without changing the repository or publishing.

For a real release, disable `dry_run`. The workflow:

1. Updates `VERSION`, `bumpversion.yaml.dist`, and the curated Unreleased section.
2. Checks the dated release notes and runs `make verify`.
3. Commits the release and pushes its annotated tag and `master` atomically.
4. Publishes the verified PHAR, checksum, and manifest to GitHub with only that
   version's curated release notes.
5. Confirms Packagist exposes the version at the exact release commit.
6. Refreshes GitHub Pages from the released commit.

Release commits use the dispatching GitHub account's public noreply identity.
The PHAR and Composer installation both report the version from `VERSION`.
Concurrent releases are serialized. A rejected push publishes nothing; inspect
and rerun preparation after resolving the branch or tag conflict.

### Packagist

The repository's Packagist GitHub push webhook indexes new tags automatically.
No publishing credentials are needed for that path. Optionally configure
`PACKAGIST_USERNAME` and a safe `PACKAGIST_TOKEN` as GitHub Actions secrets to
request an immediate refresh through the [Packagist API](https://packagist.org/apidoc).
Credentials never belong in source files or URLs.

The workflow polls Packagist for up to five minutes and fails if the version is
missing or references a different commit. If indexing is delayed, check the webhook
and rerun the failed job. GitHub release publishing is idempotent: reruns verify
every selected asset rather than replacing published assets.

### Preparing locally

Start from a clean checkout with dependencies installed:

```sh
composer release:prepare -- 0.2.0
make verify
```

This updates release files locally without committing, tagging, or publishing.
After review, commit the release and push its `vX.Y.Z` tag to trigger the same
GitHub and Packagist publishing pipeline. Tag-triggered releases require the tag,
`VERSION`, release configuration, and dated changelog section to agree.
