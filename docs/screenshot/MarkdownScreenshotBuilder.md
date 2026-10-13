# MarkdownScreenshotBuilder

Convert Markdown files into an image. The Markdown is converted to HTML with league/commonmark, injected into a
wrapper, and captured by Chrome.

## Basic usage

The wrapper uses the same `{{ toHTML "file.md" }}` directive as the
[MarkdownPdfBuilder](../pdf/MarkdownPdfBuilder.md#the-tohtml-directive). In a **Twig** wrapper, put it inside
`{% verbatim %}`:

```twig
<!doctype html>
<html lang="en">
    <head><meta charset="utf-8"><title>Preview</title></head>
    <body>
        {% verbatim %}{{ toHTML "content.md" }}{% endverbatim %}
    </body>
</html>
```

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromeScreenshotInterface $chromeScreenshot): Response
    {
        return $chromeScreenshot->markdown()
            ->wrapper('wrapper.html.twig')
            ->files('content.md')
            ->generate()
            ->stream()
        ;
    }
}
```

## Available methods

The methods from [HtmlScreenshotBuilder](./HtmlScreenshotBuilder.md) are available, except `content()` /
`contentFile()` / `contentRaw()`, which are replaced by:

| Method | Description |
|---|---|
| `files(Stringable\|string ...$paths)` | Markdown files to convert |
| `wrapper(string $template, array $context = [])` | Twig wrapper template |
| `wrapperFile(string $path)` | HTML wrapper file |
| `wrapperRaw(string $html)` | Raw HTML wrapper string |

The capture is full page by default (`clip()` = viewport only).

## Examples

### Markdown preview as WebP

```php
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;

$chromeScreenshot->markdown()
    ->wrapper('styled_wrapper.html.twig')
    ->files('content.md')
    ->width(1200)
    ->format(ScreenshotFormat::Webp)
    ->quality(80)
    ->generate()
    ->stream()   // Content-Type: image/webp
;
```
