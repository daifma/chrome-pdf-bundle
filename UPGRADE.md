# Upgrade guide

## From the initial commit to Unreleased

This release hardens the bundle's defaults and aligns several behaviours with GotenbergBundle. Read every section:
some changes can make existing conversions fail, or produce different output.

### Chrome sandbox enabled by default

Before, Chrome was always started with `--no-sandbox`. Now the sandbox is **on**.

If PHP runs as `root` (containers, some CI runners) or on a kernel without unprivileged user namespaces, Chrome will
not start any more, with errors like `Running as root without --no-sandbox is not supported` or `No usable sandbox!`.

**Fix:** run PHP as an unprivileged user (recommended). Otherwise, opt out explicitly:

```yaml
daif_chrome_pdf:
    no_sandbox: true
```

See [docs/security.md](docs/security.md#chrome-sandbox).

### TLS certificate errors no longer ignored

Before, `--ignore-certificate-errors` was always set. Now pages or resources served with self-signed, expired or
otherwise invalid certificates fail to load (`ERR_CERT_*`).

**Fix:** install your internal CA in the system trust store. Or, for development only:

```yaml
when@dev:
    daif_chrome_pdf:
        ignore_certificate_errors: true
```

### `file://` access restricted to the conversion directory

Before, rendered HTML could load any local file, and `url()` accepted `file://` targets. Now:

- Chrome can only read `file://` URLs inside the per-conversion temporary directory (where `index.html` and the
  assets are written).
- `url()` / `route()` accept only `http` and `https` targets. `url('file:///...')`, `data:`, `about:`,
  `javascript:` and `chrome:` targets throw `Daif\ChromePdfBundle\Exception\BrowserException`.

**Fix:** reference local files through `chrome_pdf_asset()`, `assets()` / `addAsset()` or `downloadFrom()`. For
local HTML files, use the HTML builder (`contentFile()`) instead of `url('file://...')`.

### Screenshots are full page by default

Before, a screenshot captured only the `width` x `height` viewport. Now, like Gotenberg, it captures the **whole
page**, and `clip(true)` captures the viewport only.

**Fix:** to keep the old output, add `->clip()` or set it in configuration:

```yaml
daif_chrome_pdf:
    default_options:
        screenshot:
            html: { clip: true }
            url: { clip: true }
            markdown: { clip: true }
```

### Screenshot `Content-Type`

Before, `stream()` always sent `Content-Type: application/pdf`, even for images. Now screenshots are sent as
`image/png`, `image/jpeg` or `image/webp`, depending on `format()`. If a client, a test, or a proxy rule relied on the
old header, update it.

### Other changes you may notice

- **Screenshot builders lose `header()` / `footer()`** (and `headerRaw`, `footerRaw`, `headerFile`, `footerFile`).
  They had no effect. Remove the calls and any `header` / `footer` key under `default_options.screenshot.*`.

- **Timeout**: the default is now 30 s (before: chrome-php's 5 s) and applies to every step. Slow conversions that
  used to fail may now succeed. Very slow ones fail with a `BrowserException` after `timeout`.
- **PDF options are honoured**: `singlePage()`, `omitBackground()`, `generateDocumentOutline()` and
  `generateTaggedPdf()` were silently ignored before. If you set them, the output changes.
- **`waitForExpression()` polls** until the expression is truthy (before: evaluated once).
  **`skipNetworkIdleEvent(false)`** now really waits for network idle, which can make conversions slower.
- **Chrome debug log**: the application logger is no longer used as chrome-php's `debugLogger`. If you relied on
  the CDP log, set `browser_options: { debugLogger: 'php://stderr' }` (or a logger service).
- **`customFlags`**: flags in `browser_options.customFlags` are now added to the bundle defaults. They no longer
  remove `--disable-dev-shm-usage`.
- **Header or footer alone**: the missing part is now empty, instead of showing Chrome's default date/title/URL line.
- **Asset name collisions**: two different files with the same base name now throw a `BrowserException` instead of
  overwriting each other.
- **`6pc`** (picas) is now one inch, not 6 inches.
- **Markdown**: a `{{ toHTML "x.md" }}` directive naming a file that was not passed to `files()` now throws a
  `PartRenderingException` instead of staying in the output. Markdown is now converted as GitHub Flavored Markdown,
  so tables, task lists and strikethrough render where they were printed as plain text before.
- **Cookies**: `Symfony\Component\HttpFoundation\Cookie` objects are now accepted (they crashed before).
- **Windows**: the browser is found automatically (including Microsoft Edge), and `ext-sockets` must be enabled in
  `php.ini`. See [docs/chrome-installation.md](docs/chrome-installation.md#windows).
