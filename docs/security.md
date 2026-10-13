# Security

This bundle starts a real web browser on your application server. Chrome runs JavaScript, follows redirects and
loads sub-resources (images, CSS, fonts, iframes, XHR). So anything that ends up in the HTML you render can make the
server issue requests or read files. The defaults are strict, and this page explains each protection.

## Summary of defaults

| Setting | Default | Effect |
|---|---|---|
| `no_sandbox` | `false` | Chrome keeps its OS-level sandbox. |
| `ignore_certificate_errors` | `false` | Invalid TLS certificates make the navigation fail. |
| `file://` access | always restricted | Only files inside the conversion's private temp directory can be loaded. |
| `url()` / `route()` targets | filtered | Only `http` / `https` targets are accepted. `allow_list` and `deny_list` apply. |
| `allow_list` / `deny_list` | empty | Every http(s) URL is allowed until you configure them. |

Versions before the hardening release had the sandbox disabled and certificate errors ignored. See
[UPGRADE.md](../UPGRADE.md).

## Chrome sandbox

The sandbox isolates Chrome's renderer processes from the operating system. A malicious page that exploits a renderer
bug cannot easily escape to the host. Keep it on.

The sandbox needs:

- **Linux:** a non-root user and unprivileged user namespaces (the default on Debian, Ubuntu, RHEL 8+). If Chrome runs
  as `root`, it refuses to start with the sandbox, and you get errors such as
  `Running as root without --no-sandbox is not supported` or `Chrome failed to start`.
- **Windows / macOS:** nothing special. It works out of the box.

Only set `no_sandbox: true` when you cannot avoid it (a container running as root, a hardened kernel without user
namespaces). In that case, compensate with container isolation, a dedicated low-privilege user, and strict
`allow_list` / `deny_list` rules.

```yaml
daif_chrome_pdf:
    no_sandbox: '%env(bool:CHROME_NO_SANDBOX)%'
```

## Local files (`file://`)

Rendered HTML (from `content()`, `contentFile()`, `contentRaw()`, markdown) is written to a **private temporary
directory** created inside `temp_dir` for every conversion, and Chrome opens it from there.

Chrome may load `file://` URLs **only inside that directory**. A request such as
`<iframe src="file:///etc/passwd">`, `<img src="file:///C:/Windows/win.ini">` or `../../` path traversal is blocked
before Chrome reads anything. Assets added with `assets()` / `addAsset()` / `chrome_pdf_asset()` and files fetched with
`downloadFrom()` are copied **into** that directory, so they keep working.

This rule is always active and cannot be turned off. If you need to show a file from elsewhere, add it as an asset.

## Request filtering

Every URL Chrome is about to load goes through the same filter: the main page, redirects, and every sub-resource.

1. `file://`: allowed only inside the conversion temp directory (see above). The lists do not change this.
2. Any other scheme except `data:`, `blob:` and `about:` (so `http`, `https`, `ws`, `ftp`...): if `allow_list` is
   non-empty, the URL must match **at least one** pattern.
3. If the URL matches **any** `deny_list` pattern, it is blocked, even when it also matches the allow list. This
   also applies to `data:`, `blob:` and `about:` URLs.

The targets of `url()` and `route()` are checked **before** navigation. Only `http` and `https` targets are
accepted: `file:`, `data:`, `about:`, `javascript:`, `chrome:` and any other scheme throw
`Daif\ChromePdfBundle\Exception\BrowserException` (`Only http and https URLs can be converted, "..." given.`). An
http(s) target blocked by the lists throws the same exception. A blocked sub-resource is aborted (the image or CSS is
missing, and a warning is logged). If `failOnResourceLoadingFailed()` is set, the conversion then fails with a
message that gives the reason (`the URL does not match the allow list`, `the URL matches the deny list`, `local files
outside the conversion directory are not accessible`).

Patterns are PCRE expressions **with delimiters**, matched against the full URL:

```yaml
daif_chrome_pdf:
    # Only render pages from our own apps and Google Fonts
    allow_list:
        - '#^https://(www\.)?example\.com/#'
        - '#^https://fonts\.(googleapis|gstatic)\.com/#'
    # Block internal infrastructure and cloud metadata endpoints (SSRF)
    deny_list:
        - '#^https?://(localhost|127\.\d+\.\d+\.\d+|\[::1\])#i'
        - '#^https?://169\.254\.#'                      # cloud metadata
        - '#^https?://(10|192\.168|172\.(1[6-9]|2\d|3[01]))\.#'
```

> [!WARNING]
> Patterns match URLs, not resolved IP addresses. A public hostname that resolves to a private IP gets past a
> hostname-only deny list. If you render user-supplied URLs, use a strict `allow_list`, and also enforce egress rules
> at the network level (firewall, proxy).

> [!WARNING]
> `downloadFrom()` fetches files in PHP, before Chrome starts. Its URLs go through the same `allow_list` /
> `deny_list` (http and https only), and redirects are not followed so a 3xx cannot point outside the lists. With
> empty lists nothing is blocked: use a strict `allow_list` if URLs come from users.

> [!NOTE]
> `route()` usually points to your own application (often `http://localhost` in CLI). If you deny localhost, either
> set `request_context.base_uri` to a host you allow, or add an allow rule for that host.

## TLS certificate errors

With `ignore_certificate_errors: false` (default), a self-signed or expired certificate makes navigation fail. On an
intranet, prefer adding your internal CA to the system trust store (Linux: `update-ca-certificates` /
`update-ca-trust`; Windows: the *Trusted Root Certification Authorities* store of the account that runs PHP). Enable
the option only in development, or when you accept man-in-the-middle risks:

```yaml
when@dev:
    daif_chrome_pdf:
        ignore_certificate_errors: true
```

## Untrusted HTML and Markdown

If any part of the content comes from users (comments, rich-text fields, uploaded Markdown):

- **Escape it.** Twig auto-escaping protects `{{ variable }}`. Never pass user input through `|raw`, `contentRaw()` or
  `headerRaw()`/`footerRaw()` without sanitising it first (for example with
  [symfony/html-sanitizer](https://symfony.com/doc/current/html_sanitizer.html)).
- **Markdown keeps raw HTML.** The Markdown builders use league/commonmark's `GithubFlavoredMarkdownConverter`
  with raw HTML allowed (only `javascript:` style links are removed), so HTML inside a `.md` file reaches Chrome
  unchanged, event handlers included. Treat Markdown files as trusted input. For untrusted Markdown, convert it
  yourself (for example with `new GithubFlavoredMarkdownConverter(['html_input' => 'strip'])`), sanitise the result,
  and render it with the HTML builder.
- **JavaScript runs.** A `<script>` in user content runs in Chrome with network access. Combine sanitising with
  `allow_list` / `deny_list`.
- **Bound the cost.** Hostile content can loop forever or load huge resources. The global `timeout` stops runaway
  conversions. Rate-limit endpoints that generate documents.
- **Cookies and headers** set with `cookies()`, `forwardCookie()` and `extraHttpHeaders()` are sent to **every** host
  the page contacts, as long as the cookie domain or header scope matches. Don't forward session cookies when you
  render third-party URLs.

## Run Chrome as a dedicated user

Chrome inherits the operating-system account of the PHP process (FPM pool user, IIS application pool identity,
Messenger worker user):

- Use a dedicated, unprivileged account (for example a `pdf` FPM pool, or a separate Messenger worker running as `pdf`)
  that can read the `assets_directory`, write to `temp_dir`, and nothing else.
- Never run it as `root` / `LocalSystem`. Besides the sandbox problem, a compromised renderer would have full control
  of the host.
- Restrict outbound network access for that account where your platform allows it.
- Keep the browser up to date: Chrome security fixes are released every few weeks, and package-managed installs
  (`apt`, `dnf`, MSI with Google Update / Edge Update) pick them up automatically.

## Reporting a vulnerability

Please report security issues privately to the maintainer (see `composer.json` authors) rather than in a public
issue.
