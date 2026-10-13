# Security Policy

## Supported versions

The project is in its 0.x series. Security fixes are made on the `main` branch and released in the next version.
Only the latest release receives fixes.

## Reporting a vulnerability

Please do not open a public issue for a security problem.

Send a private report to the maintainer at the email address listed in the `authors` section of
[composer.json](composer.json) (daif.abderrahman@gmail.com). Include:

- the bundle version, PHP version, Symfony version, operating system and browser version;
- the configuration involved (`no_sandbox`, `allow_list`, `deny_list`, ...);
- a description of the issue and, if possible, a minimal reproducer.

You will get an answer as soon as possible. Once a fix is available, the issue will be disclosed in the
[changelog](CHANGELOG.md).

## Hardening guide

This bundle starts a real browser on your server. The defaults (Chrome sandbox on, `file://` access limited to the
conversion directory, request filtering, TLS errors not ignored) and how to configure them are described in
[docs/security.md](docs/security.md).
