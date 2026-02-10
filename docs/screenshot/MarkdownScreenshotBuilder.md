# MarkdownScreenshotBuilder

Convert Markdown files into a screenshot. The Markdown is first converted to HTML, then captured as an image via Chrome.

## Basic usage

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromeScreenshotInterface;

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

All methods from [HtmlScreenshotBuilder](./HtmlScreenshotBuilder.md) are available, plus:

| Method | Description |
|---|---|
| `files(Stringable\|string ...$paths)` | Markdown files to convert |
| `wrapper(string $template, array $context)` | Twig wrapper template |
| `wrapperFile(string $path)` | HTML wrapper file |

## Examples

### Markdown screenshot with custom dimensions

```php
$chromeScreenshot->markdown()
    ->wrapper('styled_wrapper.html.twig')
    ->files('content.md')
    ->width(1200)
    ->height(800)
    ->format('png')
    ->generate()
    ->stream()
;
```
