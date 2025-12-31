# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog and Semantic Versioning.

## [Unreleased]

## [1.0.0] - 2025-12-31
### Added
- Initial WP-CLI package.
- Interactive `wp admin-email` command.
- Automatic detection of single-site vs multisite.
- Network table display of site URLs and admin_email values.
- Interactive prompts to update one site or all sites.
- Non-interactive subcommand: `wp admin-email set <email>`.
- Support for `--network`, `--url=<siteurl>`, and `--dry-run`.
