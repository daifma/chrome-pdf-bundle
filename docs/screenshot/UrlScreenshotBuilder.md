# UrlScreenshotBuilder

Capture an image of a URL or of a Symfony route.

## Basic usage

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromeScreenshotInterface $chromeScreenshot): Response
    {
        return $chromeScreenshot->url()
            ->url('https://example.com')
            ->generate()
            ->stream()
        ;
    }
}
```

### From a Symfony route

```php
$chromeScreenshot->url()
    ->route('dashboard', ['user' => 42])
    ->generate()
    ->stream()
;
```

The same rules as the [UrlPdfBuilder](../pdf/UrlPdfBuilder.md) apply: the URL is loaded by Chrome in a separate
request, only `http` and `https` targets are accepted, and `allow_list` / `deny_list` are enforced (see
[security](../security.md#request-filtering)).

## Available methods

All methods from [HtmlScreenshotBuilder](./HtmlScreenshotBuilder.md) are available, plus:

| Method | Description |
|---|---|
| `url(string $url)` | URL of the page to capture |
| `route(string $name, array $parameters = [])` | Symfony route to capture |

## Examples

### Above-the-fold capture of an external page

```php
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;

$chromeScreenshot->url()
    ->url('https://example.com')
    ->width(1280)
    ->height(800)
    ->clip()                         // viewport only; default is full page
    ->deviceScaleFactor(2.0)
    ->format(ScreenshotFormat::Png)
    ->failOnHttpStatusCodes([499, 599])
    ->generate()
    ->stream()
;
```
