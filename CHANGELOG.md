# Changelog
All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

## [1.0.0] - 2025-12-31
### Added
- New WP-CLI command: `wp admin-email` (interactive) to detect single site vs multisite, display current admin_email(s), and optionally update.
- Subcommand: `wp admin-email set <email>` with `--network`, `--url=<siteurl>`, and `--dry-run` options.
