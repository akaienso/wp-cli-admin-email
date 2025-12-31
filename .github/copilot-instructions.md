# AI Coding Agent Instructions

## Project Overview

**wp-cli-admin-email** is an operator-focused WP-CLI package (single PHP file) for viewing and updating the WordPress `admin_email` option across single-site and multisite installations. The package runs entirely within WP-CLI with no plugin dependencies.

**Key architectural principle**: Clear, explicit code is preferred over clever abstractions. This is not a framework—it's a focused operational tool meant for safe, repeatable use in production.

## Single-File Architecture

The entire implementation lives in [admin-email-command.php](../admin-email-command.php):
- One class: `Admin_Email_Command` registered as a WP-CLI command via `add_command()`
- Two public methods: `__invoke()` for interactive mode, `set()` for non-interactive scripting
- Seven private helper methods handling UI, updates, and pagination
- No external dependencies beyond WP-CLI 2.0+

**Why single file?**: Simplicity for operators. No need to navigate multiple files or understand class hierarchies.

## Two Execution Paths (Interactive vs. Non-Interactive)

### Interactive Mode: `__invoke()` 
- Entry point when user runs `wp admin-email` (no subcommand)
- **Single-site flow**: Prompt loop with options [L]ist, [S]et email, [H]elp, [Q]uit
- **Multisite flow**: Paginated table (50 sites max per page), same menu options
- Uses `\cli\prompt()` and `\cli\confirm()` from the `cli` library
- Supports `--dry-run` flag (shows changes without writing)

### Non-Interactive Mode: `set()`
- Direct setter: `wp admin-email set new@example.com [--network|--url=...|--dry-run]`
- On multisite: requires either `--network` (all sites) or `--url=<siteurl>` (specific site)
- On single-site: ignores network flags, updates that one site
- Supports `--dry-run` for validation without database writes

## Critical Helper Methods

| Method | Purpose |
|--------|---------|
| `render_single_site_table()` | Displays admin_email using WP-CLI's table formatter |
| `render_network_table()` | Pagination wrapper that calls `get_sites()` in chunks |
| `update_one_site()` | Switches blog context, updates via `update_option()` |
| `update_all_sites()` | Loops all sites from `get_sites([ 'number' => 0 ])` |
| `page_lines()` | Terminal paging for README.md viewer (terminal height aware) |
| `show_help()` | Loads README.md, builds TOC from Markdown headings, pages it |

## Multisite-Specific Patterns

- **Blog switching**: Always use `switch_to_blog()` / `restore_current_blog()` pairs
- **Site discovery**: Use `get_sites()` with `[ 'number' => X, 'offset' => Y ]` for pagination
- **Blog ID lookup**: Use `url_to_blogid()` to find a blog by its site URL
- **Database writes**: All happen via `update_option()` (respects WordPress option hooks)

## Important Conventions

### Dry-Run Flag
- Flows through both methods as a boolean parameter
- No database writes occur; output is prefixed with `[DRY RUN]`
- Useful for testing large network changes before committing

### Confirmation Before Changes
- `confirm_or_abort()` prints target, email, and mode, then calls `\cli\confirm()`
- Throwing an `\WP_CLI::error()` aborts the operation cleanly
- Applies to both interactive and non-interactive paths

### Pagination & Terminal Height
- `get_page_size()` reads `LINES` env var or defaults to 24 (clamped 10–50)
- Used by both `page_lines()` (help viewer) and `render_network_table()` (site list)
- Reserve 2 lines for prompt/footer

### Error Handling
- Use `\WP_CLI::error()` for fatal errors (halts execution)
- Use `\WP_CLI::warning()` for recoverable issues (prompts again)
- Use `\WP_CLI::success()` for successful updates
- Use `\WP_CLI::log()` for informational messages

## Testing & Development

**No automated tests in the repo.** Manual testing is the standard:
1. Test against a local single-site WordPress install
2. Test against multisite (create a test network)
3. Verify both interactive (`wp admin-email`) and non-interactive (`wp admin-email set ...`) paths
4. Verify `--dry-run` flag shows correct output without writing

**Installation for development**:
```bash
wp package install --dev /path/to/wp-cli-admin-email
```

## Changelog Policy

[CHANGELOG.md](../CHANGELOG.md) is the source of truth for user-visible changes:
- Format follows [Keep a Changelog](https://keepachangelog.com/) + [Semantic Versioning](https://semver.org/)
- Every PR that changes code must add one bullet under `[Unreleased]` (Added/Changed/Fixed/Removed)
- Docs-only or CI-only PRs may use the `skip-changelog` label to opt out
- Changelog updates are enforced by GitHub Actions CI

## Key Files

- [admin-email-command.php](../admin-email-command.php) — The entire implementation
- [README.md](../README.md) — User documentation, displayed interactively via `[H]elp`
- [CONTRIBUTING.md](../CONTRIBUTING.md) — Contribution workflow and changelog rules
- [SECURITY.md](../SECURITY.md) — Security reporting and intended behavior boundaries
- [composer.json](../composer.json) — Package metadata; type is `wp-cli-package`

## When Adding Features

1. **Prefer explicit code** over abstraction (e.g., duplication in update paths is fine if it clarifies intent)
2. **Extend the single class** — do not split into multiple files
3. **Update README.md** with user-facing documentation
4. **Add to CHANGELOG.md** under `[Unreleased]` before opening a PR
5. **Test both interactive and non-interactive modes** if your change affects command behavior
6. **Maintain interface stability**: The `wp admin-email` command name, `set` subcommand, and flags (`--network`, `--url`, `--dry-run`) are considered stable for versioning
