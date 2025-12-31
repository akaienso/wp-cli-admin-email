# Security Policy

## Intended Behavior

This project provides a WP-CLI command that updates the WordPress `admin_email` option without triggering WordPress’s built-in email confirmation workflow.

This behavior is intentional.

The tool is designed for trusted operators managing WordPress environments at scale, where administrative email addresses are already collected, verified, and approved outside of WordPress itself.

This is not considered a security vulnerability.

This tool does not alter WordPress user accounts, authentication, or role permissions.

## Reporting Security Issues

If you believe you have found a genuine security issue (for example, unintended privilege escalation, data exposure, or remote execution):

- Please open a GitHub Issue with clear reproduction steps, or 
- Submit a Pull Request if you have a proposed fix

Do not report issues that are the result of running this tool in untrusted or misconfigured environments.
