# UrlScreenshotBuilder

Generate a screenshot from a URL.

## Basic usage

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;

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

## Available methods

All methods from [HtmlScreenshotBuilder](./HtmlScreenshotBuilder.md) are available, plus:

| Method | Description |
|---|---|
| `url(string $url)` | URL of the page to capture |
| `route(string $name, array $parameters)` | Symfony route to capture |

## Examples

### Screenshot of an external page

```php
$chromeScreenshot->url()
    ->url('https://example.com')
    ->width(1280)
    ->height(800)
    ->format('png')
    ->generate()
    ->stream()
;
```
