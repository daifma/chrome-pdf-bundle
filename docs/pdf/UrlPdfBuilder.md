# UrlPdfBuilder

Generate a PDF from a URL or from a route of your Symfony application.

## Basic usage

### From a URL

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->url()
            ->url('https://example.com')
            ->generate()
            ->stream()
        ;
    }
}
```

### From a Symfony route

```php
return $chromePdf->url()
    ->route('home', ['my_var' => 'value'])
    ->generate()
    ->stream()
;
```

The route is generated as an **absolute URL**, and Chrome loads it with a separate HTTP request. It does not reuse the
current PHP request or session. Forward what the page needs with `forwardCookie()` / `extraHttpHeaders()`. In CLI or
Messenger workers, set `request_context.base_uri` (see [configuration](../configuration.md)).

> [!WARNING]
> PHP's built-in server (`php -S`, `symfony serve` without workers) handles **one request at a time**. Rendering one
> of its own routes from inside a request deadlocks until `timeout`. Use PHP-FPM, or a second server instance.

## Request filtering

Before navigating, the target URL goes through the [request filter](../security.md#request-filtering):

- Only `http` and `https` targets are accepted. `file:`, `data:`, `about:`, `javascript:`, `chrome:` and other
  schemes throw a `BrowserException`.
- If `allow_list` is configured, the URL must match it.
- If the URL matches `deny_list`, it is refused.

The same rules apply to every redirect and sub-resource the page loads.

## Available methods

All methods from [HtmlPdfBuilder](./HtmlPdfBuilder.md) are available (page properties, header/footer, waiting,
emulation, cookies and headers, error handling, `downloadFrom`), plus:

| Method | Description |
|---|---|
| `url(string $url)` | URL of the page to convert |
| `route(string $name, array $parameters = [])` | Symfony route to convert |

`url()` and `route()` are mutually exclusive.

## Examples

### External page with cookies, failing on HTTP errors

```php
$chromePdf->url()
    ->url('https://intranet.example.com/dashboard')
    ->cookies([
        ['name' => 'session', 'value' => 'abc123', 'domain' => 'intranet.example.com'],
    ])
    ->failOnHttpStatusCodes([499, 599])
    ->waitForSelector('#dashboard.loaded')
    ->generate()
    ->stream()
;
```

### Internal route, forwarding the current user's session

```php
use Daif\ChromePdfBundle\Enumeration\PaperSize;

$chromePdf->url()
    ->route('report_page', ['id' => 42])
    ->forwardCookie('PHPSESSID')
    ->paperStandardSize(PaperSize::A4)
    ->landscape()
    ->generate()
    ->stream()
;
```

### Single-page PDF of a long page

```php
$chromePdf->url()
    ->url('https://example.com/long-article')
    ->singlePage()
    ->printBackground()
    ->generate()
    ->stream()
;
```

## Troubleshooting

- **`BrowserException: ... blocked ...`**: the target or a resource does not match `allow_list`, or matches
  `deny_list`. The message gives the reason.
- **Login page in the PDF**: Chrome has no session. Forward cookies or headers.
- **Timeouts on pages with long-polling, analytics or websockets**: keep `skipNetworkIdleEvent` at its default
  (`true`), and use `waitForSelector()` instead.
- **`ERR_CERT_*`**: see [security → TLS](../security.md#tls-certificate-errors).
