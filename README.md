# wp-cli-admin-email

A WP-CLI package that adds the command:

`wp admin-email`

This allows you to view and update the `admin_email` option for WordPress
single-site and multisite installations, with interactive safety prompts.

## Installation

```bash
wp package install https://github.com/akaienso/wp-cli-admin-email.git
```

## Usage

### Interactive

`wp admin-email`

`wp admin-email --dry-run`

### Non-interactive

`wp admin-email set user@example.com`

`wp admin-email set user@example.com --network`

`wp admin-email set user@example.com --url=https://example.com/subsite/`

`wp admin-email set user@example.com --dry-run`

