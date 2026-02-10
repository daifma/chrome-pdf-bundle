# HtmlScreenshotBuilder

Convert HTML or Twig files into a screenshot (PNG, JPEG, or WebP).

## Basic usage

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;

class YourController
{
    public function yourControllerMethod(ChromeScreenshotInterface $chromeScreenshot): Response
    {
        return $chromeScreenshot->html()
            ->content('page.html.twig', [
                'my_var' => 'value'
            ])
            ->generate()
            ->stream()
        ;
    }
}
```

### From an HTML file

```php
$chromeScreenshot->html()
    ->contentFile('../templates/page.html')
    ->generate()
    ->stream()
;
```

## Available methods

### Screenshot properties

| Method | Description |
|---|---|
| `width(int $width)` | Device screen width in pixels (default: 800) |
| `height(int $height)` | Device screen height in pixels (default: 600) |
| `clip(bool $bool)` | Clip screenshot to device dimensions |
| `format(string $format)` | Image format: "png", "jpeg", or "webp" (default: png) |
| `quality(int $quality)` | Compression quality 0-100 (jpeg only, default: 100) |
| `omitBackground(bool $bool)` | Transparent background |
| `optimizeForSpeed(bool $bool)` | Optimize encoding for speed |

### Content

| Method | Description |
|---|---|
| `content(string $template, array $context)` | Set Twig template |
| `contentFile(string $path)` | Set HTML file |

### Assets

| Method | Description |
|---|---|
| `assets(Stringable\|string ...$paths)` | Set asset files |
| `addAsset(Stringable\|string $path)` | Add a single asset file |

### Wait / Rendering

| Method | Description |
|---|---|
| `waitDelay(string $delay)` | Wait duration before capturing |
| `waitForExpression(string $expression)` | Wait for JS expression |
| `emulatedMediaType(EmulatedMediaType $mediaType)` | Emulate "screen" or "print" |
| `skipNetworkIdleEvent(bool $bool)` | Skip network idle event |

### Cookies & Headers

| Method | Description |
|---|---|
| `cookies(array $cookies)` | Set cookies |
| `addCookies(array $cookies)` | Add cookies |
| `extraHttpHeaders(array $headers)` | Set extra HTTP headers |
| `userAgent(string $userAgent)` | Override User-Agent |

### Error handling

| Method | Description |
|---|---|
| `failOnHttpStatusCodes(array $statusCodes)` | Fail on specific HTTP status codes |
| `failOnConsoleExceptions(bool $bool)` | Fail on Chromium console exceptions |

## Examples

### Full-page JPEG screenshot

```php
$chromeScreenshot->html()
    ->content('page.html.twig')
    ->width(1920)
    ->height(1080)
    ->format('jpeg')
    ->quality(85)
    ->generate()
    ->stream()
;
```

### Transparent PNG screenshot

```php
$chromeScreenshot->html()
    ->content('widget.html.twig')
    ->width(400)
    ->height(300)
    ->omitBackground()
    ->generate()
    ->stream()
;
```
