# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Behaviour changes are explained in [UPGRADE.md](UPGRADE.md).

### Added

- Gotenberg parity (Chromium features):
  - `contentRaw()`, `headerRaw()`, `footerRaw()` to render raw HTML strings.
  - `waitForSelector()`, and `waitForExpression()` now polls until the expression is truthy.
  - `skipNetworkAlmostIdleEvent()`. `skipNetworkIdleEvent(false)` now really waits for network idle.
  - `failOnHttpStatusCodes()`, `failOnResourceHttpStatusCodes()`, `ignoreResourceHttpStatusDomains()`,
    `failOnResourceLoadingFailed()`, `failOnConsoleExceptions()` (`X99` matches the whole `X00–X99` range).
  - `emulatedMediaFeatures()` (e.g. `prefers-color-scheme: dark`).
  - `deviceScaleFactor()` for screenshots.
  - `downloadFrom()`, which downloads remote files with Symfony HttpClient and adds them as assets (or as Markdown
    files for the Markdown builders). Requires `symfony/http-client`. URLs go through `allow_list` / `deny_list`,
    redirects are not followed, files are written under `temp_dir`.
  - `wrapperRaw()` on the Markdown builders.
- Bundle configuration: `timeout`, `no_sandbox`, `ignore_certificate_errors`, `temp_dir`, `allow_list`, `deny_list`,
  plus the matching `default_options` keys for every new builder method.
- `Daif\ChromePdfBundle\Exception\BrowserException`, thrown for every engine failure (timeout, blocked request,
  fail-on rule, browser crash, missing binary) with an explicit message.
- Windows support: Chrome / Chromium / **Microsoft Edge** auto-detection with expanded `%ProgramFiles%`,
  `%ProgramFiles(x86)%`, `%LOCALAPPDATA%`; `CHROME_PATH` environment variable on every OS; valid `file:///C:/...` URLs.
- `Unit::Picas` (`pc`) is handled when converting units.
- `Symfony\Component\HttpFoundation\Cookie` objects are accepted everywhere cookies are.
- CI: Windows runner, functional browser tests on Linux and Windows.
- Documentation: security guide, Windows installation, full configuration reference, Gotenberg feature-parity table,
  troubleshooting sections.

### Changed

- **Screenshots are full page by default** (same as Gotenberg). `clip(true)` captures only the viewport.
- `header()`, `footer()` and their `Raw`/`File` variants moved to the PDF builders only (screenshots never printed
  them); the `header` / `footer` nodes are gone from `default_options.screenshot.*`.
- Markdown: `javascript:` and other unsafe link targets are removed (`allow_unsafe_links: false`).
- Screenshot responses use the matching `Content-Type` (`image/png`, `image/jpeg`, `image/webp`) instead of
  `application/pdf`.
- The PSR logger is no longer passed to chrome-php as `debugLogger` (too verbose). It still receives the bundle's
  warnings. Set `browser_options.debugLogger` to get the CDP log back.
- `browser_options.customFlags` are appended to the bundle's default flags (`--disable-dev-shm-usage`) instead of
  replacing them.
- `ChromePdfFileResult::getContentType()` returns the MIME type of the generated file.
- Default timeout raised from chrome-php's 5 s to 30 s (configurable with `timeout`).
- `chrome-php/chrome` minimum version raised to 1.16.
- PDF options are sent directly to CDP `Page.printToPDF`, so `singlePage`, `omitBackground`,
  `generateDocumentOutline` and `generateTaggedPdf` are now applied (they were silently ignored before).
  `singlePage` with `landscape` prints one portrait page whose width is the larger of the paper width and height.
  Recent Chromium versions tag PDFs by default, so `generateTaggedPdf(false)` may have no effect.
- When only a header or only a footer is set, the other one is left empty instead of showing Chrome's default
  date/title line.
- Assets referenced in header and footer templates are inlined as `data:` URIs, so `chrome_pdf_asset()` works
  there.
- The Markdown `{{ toHTML "file.md" }}` directive accepts any spacing, either quote style, HTML-escaped quotes, and
  the path given to `files()` as well as the base name. A directive naming an unknown file now throws a
  `PartRenderingException` (it was left in the output before). The Markdown builders share their implementation.
- Markdown is converted with league/commonmark's `GithubFlavoredMarkdownConverter` (tables, task lists,
  strikethrough, autolinks) instead of `CommonMarkConverter`.
- `chrome_binary` and `CHROME_PATH` values can contain `%VAR%` references, expanded from the environment.

### Fixed

- Browser tabs were never closed, which leaked memory in FPM workers and Messenger consumers.
- A crashed Chrome process stayed cached. It is now detected and restarted.
- Screenshot `clip` crashed (array passed instead of a `Clip` object). `omitBackground` was mapped to the wrong CDP
  option. `quality` with PNG threw an error.
- Unit conversion: `pc` was treated as inches. The duplicated conversion code was replaced by `Unit::parse()`.
- Asset copy silently overwrote files that shared a base name. It now throws a clear error.
- Temporary directory cleanup on Windows (file locks): retried, never fatal, logged as a warning.
- Basename and path handling for Windows paths (`C:\a\b.png`), including on Linux.
- Missing `symfony/http-client-contracts` dependency (used by `StringChunk`).
- PHPStan baseline errors.

### Security

- Chrome's **sandbox is enabled by default** (`no_sandbox: false`). It was previously always disabled.
- **TLS certificate errors are no longer ignored** by default (`ignore_certificate_errors: false`).
- **`file://` access is restricted** to the per-conversion temporary directory. Rendered HTML could previously read
  any local file (`<iframe src="file:///etc/passwd">`).
- `url()` / `route()` targets are filtered: only `http` and `https` are accepted (`file:`, `data:`, `about:`,
  `javascript:`, `chrome:` are refused), and `allow_list` / `deny_list` apply to
  the target, redirects and every sub-resource (SSRF mitigation).

## [0.1.0] - Initial commit

- First version: HTML, URL and Markdown builders for PDF and screenshots, driven by chrome-php/chrome.
