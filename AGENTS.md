# Repository guidance

Use PHP 8.3-compatible syntax. Commands and YAML form the supported public
interface; internal classes use `Janalis\Bumpversion\`.

Run `make fmt` after PHP changes and `make verify` before declaring work complete.
Keep user-facing changes under `Unreleased` in `CHANGELOG.md`. Documentation must
describe tested behavior; put local plans and reports in ignored `.cache/`.

Keep fixtures synthetic. Never commit personal paths, credentials, private URLs,
or local project data. PHAR packaging must use the isolated allowlisted builder.
Do not push, tag, or publish unless explicitly requested.
