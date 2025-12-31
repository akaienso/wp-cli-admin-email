# wp-cli-admin-email

[![WP-CLI Package](https://img.shields.io/badge/WP--CLI-Package-blue)](https://make.wordpress.org/cli/handbook/packages/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Platform](https://img.shields.io/badge/Platform-WordPress%20%7C%20WP--CLI-lightgrey)](https://wordpress.org)
[![Maintained](https://img.shields.io/badge/Maintained-Yes-brightgreen.svg)](#)

A WP-CLI command for viewing and updating the `admin_email` option on WordPress
single-site and multisite installations, designed for safe, repeatable use in
production environments.

**Changelog:**  
➡️ [CHANGELOG.md](CHANGELOG.md)

---

```bash
wp admin-email
```

This command allows you to inspect and manage administrative email addresses without relying on WordPress’s interactive confirmation flow—by design.

## Installation

Install directly from GitHub using WP-CLI’s package system:

```bash
wp package install https://github.com/akaienso/wp-cli-admin-email.git
```

Once installed, the admin-email command is available anywhere WP-CLI runs.

## Overview

When run, the command will:

- Automatically detect whether the current WordPress install is single-site or multisite
- Display the current admin_email value (or values)
- Prompt before making any changes
- Support dry-run mode for safe inspection
- Paginate output for large multisite networks

No WordPress plugins are installed. This tool runs entirely within WP-CLI.

## Interface Stability

This tool is intended to be used in operational and production environments.

The following aspects are considered stable:

- The `wp admin-email` command name
- The `set` subcommand
- Supported flags (`--network`, `--url`, `--dry-run`)
- Interactive confirmation behavior

Changes that would alter these interfaces will be versioned and documented in
the changelog.

New features may be added, but existing workflows will continue to function as documented within a major version.

## Usage

### Interactive Mode _(recommended)_

```bash
wp admin-email
```

Runs the command in interactive mode.

- On single-site installs:
   - Displays the current admin_email
   - Prompts to update or exit
- On multisite installs:
   - Displays a paginated table of site URLs and admin emails
   - Allows you to:
      - Refresh the list
      - Update one site
      - Update all sites
      - Exit safely

After any approved change, the updated values are automatically displayed.

#### Dry Run _(interactive)_

```bash
wp admin-email --dry-run
```

Shows what would change, but does not write anything to the database.
This is strongly recommended before making live changes on large networks.

### Non-interactive Mode

Non-interactive commands are useful for scripting or when you already know exactly what change you want to make.

#### Update a single-site install
```bash
wp admin-email set user@example.com
```

- Updates the `admin_email` for the current site
- Displays the updated value afterward

#### Update all sites in a multisite network

```bash
wp admin-email set user@example.com --network
```

- Updates the admin_email for every site in the network
- Displays the updated, paginated list afterward

#### Update one site in a multisite network

```bash
wp admin-email set user@example.com --url=https://example.com/subsite/
```

- Updates only the site matching the provided URL
- Displays the updated network list afterward

#### Dry-run (non-interactive)

```bash
wp admin-email set user@example.com --dry-run
```

***OR***

```bash
wp admin-email set user@example.com --network --dry-run
```

- Prints exactly what would be changed
- Makes no database writes

## Scripting with `wp admin-email`

_These examples assume WP-CLI is already configured with appropriate access._

### Example: Using `wp admin-email` in a Bash Script

The non-interactive form of this command is designed to be safely composed into shell scripts and deployment workflows.

Below is a simple example showing how an operator might update the administrative email address during a production handoff.

#### Example: Development → Production Handoff

```bash
#!/usr/bin/env bash
set -euo pipefail

# Customer-provided administrative contact
ADMIN_EMAIL="admin@customer.org"

# Optional: specific site URL in a multisite network
SITE_URL="https://example.com"

echo "Updating WordPress admin email..."

# Dry run first (recommended)
wp admin-email set "$ADMIN_EMAIL" --url="$SITE_URL" --dry-run

echo "Dry run complete. Applying change..."

# Apply the change
wp admin-email set "$ADMIN_EMAIL" --url="$SITE_URL"

echo "Admin email updated successfully."
```

### Example: Network-wide Update

```bash
#!/usr/bin/env bash
set -euo pipefail

ADMIN_EMAIL="admin@customer.org"

# Update all sites in a multisite network
wp admin-email set "$ADMIN_EMAIL" --network
```

### Notes

- Scripts should typically run the command once per environment or deployment
- Use `--dry-run` when testing or validating changes
- The command will exit with a non-zero status on errors, making it safe to use in CI/CD pipelines or automated workflows

## Output Pagination (Multisite)

For multisite networks with many sites, output is automatically paginated based on terminal height.

After each page, you will be prompted to:

- Press Enter to continue to the next page
- Press Q to quit early

This prevents large networks from dumping hundreds of rows at once.

## Safety Features

- Explicit confirmation before any live update
- Dry-run support in all modes
- Automatic re-display of values after changes
- No changes made unless explicitly approved

## Why This Exists

WordPress intentionally treats changes to the `admin_email` setting as a
user-confirmed action. When changed through the admin UI, WordPress sends a
verification email and waits for the recipient to approve the update.

That behavior makes sense for individual site owners. It becomes a serious
operational bottleneck at scale.

We manage **thousands of sites for hundreds of customer organizations**. As part
of onboarding, each customer already provides a designated point-of-contact
email address for administrative notifications. That email address is known,
verified, and contractually authoritative before a site ever goes live.

Requiring an additional confirmation email at launch creates real problems:

- Customers may not see or act on the confirmation immediately
- Launch-time system emails continue going to internal or development inboxes
- Production warnings, error notices, and critical alerts are delayed or missed
- Operations staff must manually intervene to re-trigger confirmations

This tool exists to solve that problem cleanly.

During development, sites intentionally use an internal development mailbox for
`admin_email`. At handoff, that value needs to be updated **immediately and
reliably** to the customer-provided address—without waiting for an extra click
from someone who has already supplied that information.

WP-CLI runs with administrative intent. This command uses that intent explicitly
and transparently.

---

## Common Scenarios

### Development → Production Handoff

A multisite network may contain dozens of sites in various stages of development.

- All development sites use a shared internal admin email
- At launch, each site must switch to the customer’s designated contact email
- The change must be effective immediately

```bash
wp admin-email
```

Interactive mode lets you review the current state, update one site or all sites, and verify the results before and after the change.

---

### Bulk Network Updates

A customer reorganizes internally and requests that all administrative emails be updated to a new shared address.

```bash
wp admin-email set admin@customer.org --network
```

#### Dry-run first:

```bash
wp admin-email set admin@customer.org --network --dry-run
```

### Targeted Fix for a Single Site in a Large Network

One site in a large multisite network was launched with an incorrect admin email.

```bash
wp admin-email set admin@customer.org --url=https://example.com/subsite/
```

The rest of the network remains untouched.

---

### Auditing Existing Admin Email Values

Before making changes, you want to see where administrative emails are currently pointing—especially in large networks.

```bash
wp admin-email
```

Results are paginated automatically to avoid overwhelming the terminal.

---

### A Note on Responsibility

This tool deliberately bypasses WordPress’s built-in email confirmation flow.

That is the point.

It is intended for trusted operators managing environments where:

- Administrative email addresses are already collected and validated
- Changes are intentional and auditable
- WP-CLI access implies administrative authority

If that does not describe your environment, you should not use this tool.

Used correctly, it eliminates an entire class of launch-day failures and post-launch cleanup work.

## Support and External Resources

This project is provided as-is.

I do not have the bandwidth or staff to provide direct support, troubleshooting,
or one-on-one assistance. If you encounter a bug or unexpected behavior:

- Please open a GitHub Issue with clear reproduction steps, or
- Submit a Pull Request if you have a fix or improvement to propose

Issues that do not include actionable details or that request direct support
may be closed.

That said, most questions related to administrative email handling, WordPress
options, or multisite behavior are already well-covered by official WordPress
and WP-CLI documentation and community resources.

### WordPress Core Documentation

- WordPress Settings API and Options  
  https://developer.wordpress.org/apis/settings/

- `admin_email` option behavior  
  https://developer.wordpress.org/reference/functions/update_option/  
  https://developer.wordpress.org/reference/hooks/update_option_admin_email/

- WordPress Multisite Overview  
  https://developer.wordpress.org/advanced-administration/multisite/

- Managing Multisite Networks  
  https://wordpress.org/documentation/article/manage-networks/

### WP-CLI Documentation

- WP-CLI Command Handbook  
  https://make.wordpress.org/cli/handbook/

- Working with Multisite via WP-CLI  
  https://make.wordpress.org/cli/handbook/multisite/

- WP-CLI Packages  
  https://make.wordpress.org/cli/handbook/packages/

### Community Support

- WordPress Support Forums  
  https://wordpress.org/support/

- WP-CLI GitHub Repository (issues and discussions)  
  https://github.com/wp-cli/wp-cli

- WordPress Stack Exchange  
  https://wordpress.stackexchange.com/

These resources cover the underlying systems this tool interacts with and are the appropriate places to seek help for general WordPress or WP-CLI behavior.


## How to Contribute

This project is public because shared operational tooling benefits everyone. Contributions are welcome, but with a few expectations.

### Pull Requests

If you’d like to contribute:

- Fork the repository
- Create a focused branch for your change
- Open a pull request with a clear description of:
  - what problem the change solves
  - how it behaves on both single-site and multisite installs
  - whether it affects interactive or non-interactive usage

Small, well-scoped improvements are preferred over large refactors.

### Issues and Suggestions

Bug reports, edge cases, and workflow suggestions are welcome via GitHub Issues.

When reporting issues, please include:
- whether the site is single-site or multisite
- approximate network size (for multisite)
- the command used and expected vs actual behavior

### Design Philosophy

This tool is intentionally conservative:

- Safety and clarity are prioritized over convenience
- Interactive prompts exist to prevent accidental production changes
- Behavior should be predictable and easy to reason about under pressure

Changes that reduce safety, remove confirmations, or obscure output are unlikely to be accepted.

### Code Style

- Follow existing structure and naming
- Avoid unnecessary abstractions
- Keep logic readable over clever

The goal is a tool that administrators trust—not one they have to think about.

---

Thank you for taking the time to improve this project.

## License
### MIT