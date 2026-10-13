# Configuration

You can print the full, up-to-date reference from your own application with:

```bash
php bin/console config:dump-reference daif_chrome_pdf
```

## Global options

```yaml
# config/packages/daif_chrome_pdf.yaml

daif_chrome_pdf:

    # Base directory (or list of directories) for assets, HTML files and markdown files.
    # Relative paths are resolved from %kernel.project_dir%.
    assets_directory: '%kernel.project_dir%/assets'

    # Absolute path to the Chrome / Chromium / Edge binary.
    # null = auto-detection (CHROME_PATH env var, then well-known locations, see chrome-installation.md).
    chrome_binary: null

    # Global timeout in milliseconds (minimum 1000). Applies to the browser start, navigation, waits
    # (waitForExpression, waitForSelector, network idle), printing and screenshots.
    timeout: 30000

    # Launch Chrome with --no-sandbox. Keep false unless Chrome must run as root
    # or in an environment without user namespaces. See security.md.
    no_sandbox: false

    # Ignore TLS certificate errors (self-signed, expired, wrong host).
    # Prefer installing your internal CA in the system trust store.
    ignore_certificate_errors: false

    # Directory where each conversion creates its private working directory
    # (index.html, header/footer, assets). null = sys_get_temp_dir().
    temp_dir: null

    # PCRE patterns (WITH delimiters) for http(s) URLs Chrome is allowed to load.
    # Empty = every http(s) URL is allowed (unless denied below).
    allow_list: []

    # PCRE patterns (WITH delimiters) for URLs Chrome must never load. Checked after allow_list.
    deny_list: []

    # Raw options passed to chrome-php BrowserFactory::createBrowser(), merged last
    # (they override the options computed from the keys above; customFlags are appended).
    browser_options: {}

    request_context:
        # Used only by ->route(). Overrides the base URL guessed from the current request.
        # Required in CLI / Messenger workers.
        base_uri: ~

    # Stream ChromePdfFileResult objects returned by controllers (kernel.view listener).
    controller_listener: true

    # Default options for each builder (see below).
    default_options:
        pdf:
            html: { }
            url: { }
            markdown: { }
        screenshot:
            html: { }
            url: { }
            markdown: { }
```

### `chrome_binary`

```yaml
daif_chrome_pdf:
    # Linux
    chrome_binary: '/usr/bin/chromium'
    # macOS
    # chrome_binary: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
    # Windows (single quotes: backslashes are kept as-is)
    # chrome_binary: 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
```

`%VAR%` references (Windows style) in the configured path are expanded from the environment, for example
`'%ProgramFiles%\Google\Chrome\Application\chrome.exe'`. In YAML, `%` must be doubled (`%%ProgramFiles%%`) because
single `%...%` is read as a container parameter.

Environment-friendly version. The `default::` processor falls back to `null` (auto-detection) when the variable is not set:

```yaml
daif_chrome_pdf:
    chrome_binary: '%env(default::CHROME_PATH)%'
```

### `timeout`

chrome-php waits only 5 seconds by default, which is too short for large documents. This bundle uses 30 seconds.
Raise it for heavy reports:

```yaml
daif_chrome_pdf:
    timeout: 120000 # 2 minutes
```

The timeout applies to each step (browser start, navigation, each wait, printing), not to the whole conversion, so
a conversion with several waits can take longer than this value.

When the timeout is reached, a `Daif\ChromePdfBundle\Exception\BrowserException` is thrown and the tab is closed.
Remember to keep PHP's `max_execution_time` (and your web server or FPM `request_terminate_timeout`) above this value.

### `no_sandbox` and `ignore_certificate_errors`

Both default to `false`. Read [security.md](security.md) before enabling them.

```yaml
# e.g. a container running PHP as root, talking to an intranet with a private CA
daif_chrome_pdf:
    no_sandbox: true
    ignore_certificate_errors: '%kernel.debug%'
```

### `temp_dir`

Each conversion writes `index.html`, the header and footer templates and the assets into a private sub-directory of
this folder. Chrome can read `file://` URLs **only** inside that sub-directory. The sub-directory is deleted after the
conversion.

```yaml
daif_chrome_pdf:
    temp_dir: '%kernel.project_dir%/var/chrome-pdf'
```

The PHP user must be able to create directories here, and Chrome must be able to read them. On Windows, this matters
for IIS application pool identities (see [chrome-installation.md](chrome-installation.md#windows)).

### `allow_list` / `deny_list`

Regular expressions **with delimiters**, matched against the full URL of the target page **and** of every sub-resource
(images, CSS, fonts, XHR, iframes). Invalid patterns are rejected when the container is compiled. `data:`, `blob:` and
`about:` URLs skip the allow list but not the deny list. `file://` URLs follow their own rule (conversion directory
only). `downloadFrom()` URLs are not checked. See [security.md](security.md#request-filtering).

```yaml
daif_chrome_pdf:
    allow_list:
        - '#^https://intranet\.example\.com/#'
        - '#^https://fonts\.(googleapis|gstatic)\.com/#'
    deny_list:
        - '#^https?://(127\.|10\.|192\.168\.|169\.254\.)#'
        - '#^https?://localhost#'
```

### `browser_options`

These options are passed directly to
[chrome-php `BrowserFactory::createBrowser()`](https://github.com/chrome-php/chrome#options). They are merged **last**,
so they override the values the bundle computes (`headless`, `noSandbox`, `ignoreCertificateErrors`,
`sendSyncDefaultTimeout`), with one exception: `customFlags` are **appended** to the bundle's own flags (currently
`--disable-dev-shm-usage`) instead of replacing them.

The bundle's PSR logger (channel `daif_chrome_pdf`) receives warnings (blocked requests, cleanup failures, tab close
errors). It is **not** passed to chrome-php as `debugLogger`, because the CDP traffic log is very verbose. To get it,
set `debugLogger` yourself, as below.

Use these options for advanced flags:

```yaml
daif_chrome_pdf:
    browser_options:
        windowSize: [1920, 1080]
        customFlags: ['--disable-gpu', '--font-render-hinting=none']
        envVariables: { TZ: 'Europe/Paris' }
        userDataDir: '%kernel.project_dir%/var/chrome-profile'
        debugLogger: 'php://stderr'
```

## Default options per builder

All builder methods marked with a configuration node can be preset in YAML. Keys are the snake_case version of the
method name. Values set in PHP on the builder always win over these defaults.

### PDF builders (`default_options.pdf.html`, `.url`, `.markdown`)

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            html:
                # Header / footer Twig templates (html and markdown builders)
                header:
                    template: null
                    context: []
                footer:
                    template: null
                    context: []

                # Print the entire content on one single page - default false
                single_page: null
                # "letter", "legal", "tabloid", "ledger", "A0" ... "A6"
                paper_standard_size: null
                # Units: number = inches, or a string with a unit: "210mm", "21cm", "8.5in", "612pt", "816px", "51pc"
                paper_width: null       # default 8.5in
                paper_height: null      # default 11in
                margin_top: null        # default 0.39in
                margin_bottom: null     # default 0.39in
                margin_left: null       # default 0.39in
                margin_right: null      # default 0.39in
                prefer_css_page_size: null
                generate_document_outline: null
                generate_tagged_pdf: null
                print_background: null
                omit_background: null
                landscape: null
                scale: null             # default 1.0
                native_page_ranges: null # e.g. "1-5, 8, 11-13"

                # Waiting
                wait_delay: null                    # e.g. "500ms", "5s"
                wait_for_expression: null           # e.g. "window.status === 'ready'"
                wait_for_selector: null             # e.g. "#chart-rendered"
                skip_network_idle_event: null       # default true
                skip_network_almost_idle_event: null # default true

                # Emulation
                emulated_media_type: null           # "print" (default) or "screen"
                emulated_media_features:
                    - { name: 'prefers-color-scheme', value: 'dark' }

                # Network
                cookies:
                    - { name: 'session', value: 'abc', domain: 'intranet.example.com', path: '/', secure: true, httpOnly: true, sameSite: 'Lax' }
                user_agent: null
                extra_http_headers:
                    'X-Header': 'value'
                download_from:
                    - { url: 'https://cdn.example.com/logo.png', extraHttpHeaders: { Authorization: 'Bearer xxx' } }

                # Fail on
                fail_on_http_status_codes: [499, 599]
                fail_on_resource_http_status_codes: []
                ignore_resource_http_status_domains: []
                fail_on_resource_loading_failed: null
                fail_on_console_exceptions: null

            url:
                # Same options as html
            markdown:
                # Same options as html
```

### Screenshot builders (`default_options.screenshot.html`, `.url`, `.markdown`)

```yaml
daif_chrome_pdf:
    default_options:
        screenshot:
            html:
                width: null                 # default 800
                height: null                # default 600
                device_scale_factor: null   # default 1.0 (2.0 = "retina")
                clip: null                  # default false = full page; true = viewport only
                format: null                # "png" (default), "jpeg", "webp"
                quality: null               # 0-100, ignored for png
                omit_background: null
                optimize_for_speed: null

                # Same waiting / emulation / network / fail-on options as PDF builders
                wait_delay: null
                wait_for_expression: null
                wait_for_selector: null
                skip_network_idle_event: null
                skip_network_almost_idle_event: null
                emulated_media_type: null
                emulated_media_features: []
                cookies: []
                user_agent: null
                extra_http_headers: []
                download_from: []
                fail_on_http_status_codes: []
                fail_on_resource_http_status_codes: []
                ignore_resource_http_status_domains: []
                fail_on_resource_loading_failed: null
                fail_on_console_exceptions: null
            url: { }
            markdown: { }
```

## Examples

### Header and footer default templates

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            html:
                margin_top: '2cm'      # header/footer are only visible inside the margins
                margin_bottom: '2cm'
                header:
                    template: 'pdf/header.html.twig'
                    context:
                        title: 'Hello'
                footer:
                    template: 'pdf/footer.html.twig'
                    context:
                        foo: 'bar'
```

### Extra HTTP headers

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            url:
                extra_http_headers:
                    'My-Header': 'MyValue'
                # or the list syntax
                # extra_http_headers:
                #     - { name: 'My-Header', value: 'MyValue' }
```

### Cookies

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            url:
                cookies:
                    - { name: 'yummy_cookie', value: 'choco', domain: 'example.com' }
                    - { name: 'my_cookie', value: 'symfony', domain: 'symfony.com', secure: true, httpOnly: true, sameSite: 'Lax' }
```

### Strict mode for URL rendering

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            url:
                fail_on_http_status_codes: [499, 599]          # any 4xx/5xx on the main page
                fail_on_resource_http_status_codes: [499, 599] # ... or on any sub-resource
                ignore_resource_http_status_domains: ['analytics.example.com']
                fail_on_resource_loading_failed: true
                fail_on_console_exceptions: true
```

## Controller Listener

When a controller returns something other than a `Response`, Symfony fires the
[`kernel.view`](https://symfony.com/doc/current/reference/events.html#kernel-view) event. The listener catches
`ChromePdfFileResult` objects and calls `->stream()` on them to build the response.

It is enabled by default. To disable it:

```yaml
daif_chrome_pdf:
    controller_listener: false
```
