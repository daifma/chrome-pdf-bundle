# HtmlScreenshotBuilder

Convert HTML, Twig templates or raw HTML into an image (PNG, JPEG or WebP).

## Basic usage

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromeScreenshotInterface $chromeScreenshot): Response
    {
        return $chromeScreenshot->html()
            ->content('page.html.twig', ['my_var' => 'value'])
            ->generate()
            ->stream()
        ;
    }
}
```

### From an HTML file or a raw string

```php
$chromeScreenshot->html()
    ->contentFile('../templates/page.html')   // or ->contentRaw('<!DOCTYPE html>...')
    ->generate()
    ->stream()
;
```

## Behaviour

- **Full page by default.** The capture covers the whole document height, like Gotenberg. Call `clip()` (or
  `clip(true)`) to capture only the `width` x `height` viewport.
- `width` / `height` set the **viewport** used for layout (CSS media queries, `100vh`...). With a full-page capture,
  the image is `width` wide and as tall as the document.
- `deviceScaleFactor(2.0)` produces a high-DPI ("retina") image whose pixel size is twice the CSS size.
- `quality` applies to JPEG and WebP only. It is **ignored for PNG**.
- The response `Content-Type` matches the format: `image/png`, `image/jpeg` or `image/webp`. Set the file extension
  yourself in `fileName()`.

## Available methods

### Screenshot properties

| Method | Description |
|---|---|
| `width(int $width)` | Viewport width in CSS pixels (default 800) |
| `height(int $height)` | Viewport height in CSS pixels (default 600) |
| `deviceScaleFactor(float $deviceScaleFactor)` | Device pixel ratio (default 1.0) |
| `clip(bool $bool = true)` | Capture only the viewport (default `false` = full page) |
| `format(ScreenshotFormat $format)` | `ScreenshotFormat::Png` (default), `::Jpeg`, `::Webp` |
| `quality(int $quality)` | 0-100, JPEG/WebP only |
| `omitBackground(bool $bool = true)` | Transparent background (PNG/WebP) when the page sets no background |
| `optimizeForSpeed(bool $bool = true)` | Faster encoding, larger files |

### Content

| Method | Description |
|---|---|
| `content(string $template, array $context = [])` | Twig template |
| `contentFile(string $path)` | HTML file |
| `contentRaw(string $html)` | Raw HTML string |

### Assets

| Method | Description |
|---|---|
| `assets(Stringable\|string ...$paths)` | Asset files (replaces previous ones) |
| `addAsset(Stringable\|string $path)` | Add one asset file |
| `downloadFrom(array $downloadFrom)` | Download remote files next to the HTML (see [HtmlPdfBuilder](../pdf/HtmlPdfBuilder.md#downloadfrom)) |

### Waiting

| Method | Description |
|---|---|
| `waitDelay(string $delay)` | Fixed delay after load, e.g. `'500ms'` |
| `waitForExpression(string $expression)` | Poll a JS expression until it is truthy |
| `waitForSelector(string $selector)` | Poll until the selector matches an element |
| `skipNetworkIdleEvent(bool $bool = true)` | `false` = also wait for network idle |
| `skipNetworkAlmostIdleEvent(bool $bool = true)` | `false` = also wait for network almost idle |

### Emulation

| Method | Description |
|---|---|
| `emulatedMediaType(EmulatedMediaType $mediaType)` | `Print` or `Screen` |
| `emulatedMediaFeatures(array $emulatedMediaFeatures)` | e.g. `[['name' => 'prefers-color-scheme', 'value' => 'dark']]` |

### Cookies & headers

| Method | Description |
|---|---|
| `cookies(array $cookies)` / `addCookies(array $cookies)` | Cookies (arrays or Symfony `Cookie`) |
| `setCookie(string $name, Cookie\|array $cookie)` | One cookie |
| `forwardCookie(string $name)` | Forward a cookie from the current request |
| `extraHttpHeaders(array $headers)` / `addExtraHttpHeaders(array $headers)` | Extra HTTP headers |
| `userAgent(string $userAgent)` | Override the User-Agent |

### Error handling

| Method | Description |
|---|---|
| `failOnHttpStatusCodes(array $statusCodes)` | Main document status codes that fail the capture (`499` = 400-499) |
| `failOnResourceHttpStatusCodes(array $statusCodes)` | Same, for sub-resources |
| `ignoreResourceHttpStatusDomains(array $domains)` | Hosts ignored by resource checks |
| `failOnResourceLoadingFailed(bool $bool = true)` | Fail if a sub-resource cannot be loaded |
| `failOnConsoleExceptions(bool $bool = true)` | Fail on uncaught JS exceptions |

## Examples

### JPEG of the viewport only

```php
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;

$chromeScreenshot->html()
    ->content('page.html.twig')
    ->width(1920)
    ->height(1080)
    ->clip()
    ->format(ScreenshotFormat::Jpeg)
    ->quality(85)
    ->fileName('page.jpg')
    ->generate()
    ->stream()   // Content-Type: image/jpeg
;
```

### Retina PNG with transparent background

```php
$chromeScreenshot->html()
    ->content('widget.html.twig')
    ->width(400)
    ->height(300)
    ->clip()
    ->deviceScaleFactor(2.0)   // 800x600 px image
    ->omitBackground()
    ->generate()
    ->stream()
;
```

## Troubleshooting

- **Huge images or out-of-memory errors**: a very long page produces a very tall image in full-page mode. Use
  `clip()`, or reduce `deviceScaleFactor`.
- **Image cut at 600 px**: you enabled `clip()`. Remove it to capture the full page.
- **Fonts and timeouts**: see [Chrome installation → Troubleshooting](../chrome-installation.md#troubleshooting).
