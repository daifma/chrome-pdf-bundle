# Contributing to ChromePdfBundle

Thank you for your interest in contributing to ChromePdfBundle!

## How to Contribute

### Report Issues

Found a bug or have a feature request? [Open an issue](https://github.com/daifma/chrome-pdf-bundle/issues). For bugs,
please include your OS, PHP version, browser and version (`chrome --version` / Edge version), and the bundle
configuration.

Security issues: please report them privately (see [docs/security.md](docs/security.md#reporting-a-vulnerability)).

### Submit Pull Requests

* Fork the repository.
* Create a new branch for your changes.
* Follow the existing code style, and add tests.
* Update the documentation (`docs/`, `README.md` parity table) and add an entry under `Unreleased` in
  [CHANGELOG.md](CHANGELOG.md). Behaviour changes also go in [UPGRADE.md](UPGRADE.md).
* Submit a pull request with a clear description of your changes.

## Installation

### Requirements

- PHP 8.1+ with `ext-sockets` (and `ext-mbstring` for the dev tools)
- Composer
- Chrome, Chromium or Edge for the functional browser tests (set `CHROME_PATH` if it is not auto-detected)
- qpdf, ExifTool and LibreOffice for the engine tests (optional, see [docs/engines.md](docs/engines.md); set
  `QPDF_PATH`, `EXIFTOOL_PATH`, `LIBREOFFICE_PATH` if they are not auto-detected)

### Clone and install

```shell
git clone https://github.com/daifma/chrome-pdf-bundle.git
cd chrome-pdf-bundle
composer install
```

## Testing

```shell
./vendor/bin/phpunit
./vendor/bin/phpunit --coverage-text   # optional
```

Tests that start a real browser are in the `browser` group. `CHROME_PDF_BROWSER_TESTS` controls them: `auto`
(default, run when a browser is found), `1` (a browser is required, used by the CI) or `0` (skip). You can also use
`--exclude-group browser`. Set `CHROME_PATH` if the browser is not auto-detected, and `CHROME_NO_SANDBOX=0|1` to
override the sandbox setting (disabled by default on Linux for these tests). See [docs/testing.md](docs/testing.md).

Tests that call qpdf, ExifTool or LibreOffice are skipped when the binary is not found, unless
`CHROME_PDF_ENGINE_TESTS=1` (set by the CI), which turns a missing binary into a failure.

The CI runs the suite on Linux **and Windows**. Avoid anything platform-specific:

- no `posix_*` / `pcntl_*`, no shell commands, no hard-coded `/tmp` or `/` separators;
- run external tools with `Symfony\Component\Process\Process` and an array of arguments, never a command string;
- use `Symfony\Component\Filesystem\Path` for path manipulation;
- build `file://` URLs with the Browser layer helper, never by concatenation.

## Quality Assurance

### Code style

```shell
./vendor/bin/php-cs-fixer fix --diff
```

### Static analysis

```shell
php -dmemory_limit=-1 ./vendor/bin/phpstan analyse
```

### Dependencies

```shell
./vendor/bin/composer-dependency-analyser
```

### Configuration reference

When you add a builder option with `#[WithConfigurationNode]`, check the generated configuration and update
[docs/configuration.md](docs/configuration.md):

```shell
php bin/config.dump-reference
```

---

Thank you for contributing to ChromePdfBundle!
