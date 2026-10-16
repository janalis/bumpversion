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

1. Add user-visible changes under `Unreleased` in `CHANGELOG.md`.
2. Run `make verify` and review the diff and archive manifest for confidential data.
3. Release the curated Unreleased section into a dated version section and commit the release.
4. Create and push a matching `vX.Y.Z` tag when ready to publish.

The Release workflow verifies the tagged source, builds an isolated production
PHAR, checks the archive and installed CLI, and publishes a GitHub release using `CHANGELOG.md` as release notes, with
`bumpversion.phar`, `SHA256SUMS`, and `manifest.txt`. The workflow does not create
commits or tags. Configure Packagist's normal GitHub integration separately if needed.

Running the Release workflow manually builds and uploads artifacts without
publishing. CI and release jobs use pinned actions; only the publication job gets
write access. No deployment credentials are required for local builds.
