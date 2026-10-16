# Security

Security fixes target the current maintained release line. PHP must satisfy the
package's supported runtime requirements.

Report suspected vulnerabilities through the repository's private vulnerability
reporting feature when available. If private reporting is unavailable, open an
issue requesting a private reporting channel without including exploit details
or confidential data.

Use a minimal synthetic reproduction. Remove credentials, personal information,
private URLs, and proprietary source code from reports and attachments.

Version updates write to explicitly configured project files. Review configuration
and use `--dry-run` before a release. Git hooks execute under the repository's own
Git configuration. Verify PHAR checksums before executing downloaded artifacts.
