# MarkdownPdfBuilder

Convert Markdown files into PDF. The Markdown is first converted to HTML using [league/commonmark](https://commonmark.thephpleague.com/), then rendered to PDF via Chrome.

## Basic usage

### Twig wrapper

Wrap your Markdown content in a Twig template:

```html
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>My PDF</title>
    </head>
    <body>
        {{ markdown_content }}
    </body>
</html>
```

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->markdown()
            ->wrapper('wrapper.html.twig', [
                'my_var' => 'value'
            ])
            ->files('content.md')
            ->generate()
            ->stream()
         ;
    }
}
```

### HTML wrapper

```html
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>My PDF</title>
    </head>
    <body>
        <!-- Markdown content will be inserted here -->
    </body>
</html>
```

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->markdown()
            ->wrapperFile('../templates/wrapper.html')
            ->files('content.md')
            ->generate()
            ->stream()
         ;
    }
}
```

## Available methods

All methods from [HtmlPdfBuilder](./HtmlPdfBuilder.md) are available, plus:

| Method | Description |
|---|---|
| `files(Stringable\|string ...$paths)` | Markdown files to convert |
| `wrapper(string $template, array $context)` | Twig wrapper template |
| `wrapperFile(string $path)` | HTML wrapper file |

## Examples

### Multiple Markdown files with styling

```php
$chromePdf->markdown()
    ->wrapper('styled_wrapper.html.twig', [
        'title' => 'Documentation',
    ])
    ->files('chapter1.md', 'chapter2.md', 'chapter3.md')
    ->paperStandardSize(PaperSize::A4)
    ->printBackground()
    ->generate()
    ->stream()
;
```
