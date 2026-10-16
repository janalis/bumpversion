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
the existing checksum rather than replacing published assets.

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
