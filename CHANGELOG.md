# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/) and [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

### Changed

### Fixed

### Removed

## [v1.1.0] - 2025-01-14

### Added
- `get` subcommand for non-interactive admin email retrieval
- Progress indicator for bulk network updates
- Email validation using WordPress `is_email()` function
- Input sanitization using `sanitize_email()`

### Changed
- Add `declare(strict_types=1);` for type safety
- Add namespace `Akaienso\WP_CLI` to prevent class name conflicts
- Extract common email validation logic into `prompt_and_validate_email()` method
- Replace magic numbers with named constants

### Fixed
- Add error handling for `update_option()` failures
- Fix memory issue in `update_all_sites()` by processing sites in batches instead of loading all at once
- Improve error reporting for network updates (track and report failures)

## [v1.0.5] - 2025-12-31

### Added
- Add comprehensive GitHub CoPilot instructions to repository.

## [v1.0.4] - 2025-12-31

### Fixed
- Fix CI workflow enforcing CHANGELOG.md updates on pull requests.

## [v1.0.3] - 2025-12-31

### Changed
- Enforce CHANGELOG.md updates for code changes via CI on pull requests.
- Require PRs to document user-visible changes under [Unreleased] or request `skip-changelog`.
- Improve contribution and release workflow documentation.
- GitHub Releases now publish only the tagged section of CHANGELOG.md.

## [v1.0.2] - 2025-12-31

### Changed
- Add explicit [L]ist option in interactive mode for both single-site and multisite.
- Automatically re-display current admin email status after exiting Help.

## [v1.0.1] - 2025-12-31

### Added
- Interactive Help option (`[H]elp`) that displays `README.md` in a paginated viewer.
- Generated table of contents at the top of the Help viewer (derived from Markdown headings).
- `SECURITY.md` describing intended behavior and how to report genuine security issues.

### Changed
- Interactive UX prompts updated to include Help navigation.
- After approved changes, the command automatically re-displays current admin email status (paged on multisite).
- README expanded and reorganized: clearer usage, rationale (“Why This Exists”), support boundaries, external resources, contribution guidelines, and scripting examples.
- README now links prominently to `CHANGELOG.md`.

## [v1.0.0] - 2025-12-31

### Added
- Initial WP-CLI package.
- Interactive `wp admin-email` command.
- Automatic detection of single-site vs multisite.
- Network table display of site URLs and admin_email values.
- Interactive prompts to update one site or all sites.
- Non-interactive subcommand: `wp admin-email set <email>`.
- Support for `--network`, `--url=<siteurl>`, and `--dry-run`.
