# Contributing

Thanks for your interest in contributing to `wp-cli-admin-email`.

This is an operator-focused WP-CLI package for viewing/updating the WordPress `admin_email` option in both single-site and multisite installs. Safety, predictability, and clear documentation matter more than clever abstractions.

## Ground rules

- Keep PRs small and easy to review.
- Prefer clear, explicit code over heavy refactors.
- This project does not provide direct end-user support. If you find a bug, open an issue and (ideally) submit a PR.

## Changelog policy (source of truth)

This repo treats `CHANGELOG.md` as the public source of truth for user-visible changes.

### Required for most PRs

If your PR changes code, add **one bullet** under `## [Unreleased]` describing the user-visible effect.

- Use short, user-facing language.
- Put it under the right heading: Added / Changed / Fixed / Removed.
- Do not create a new version section (the maintainer does that during release).

### When you may skip the changelog

If your PR is not user-facing (docs-only, CI-only, internal cleanup), you may omit the changelog update, but you must:

- Explain why in the PR description, and
- Request the `skip-changelog` label.

## Pull request process

1. Fork the repo and create a branch (`fix/...` or `feature/...`).
2. Make changes.
3. Update `CHANGELOG.md` under `[Unreleased]` unless `skip-changelog` applies.
4. Test what you changed (single-site and/or multisite as appropriate).
5. Open a PR and follow the PR template.

## Security issues

If you believe you’ve found a security vulnerability, follow `SECURITY.md`. For non-security bugs, open a GitHub issue with clear reproduction steps.
