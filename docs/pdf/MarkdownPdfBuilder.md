# MarkdownPdfBuilder

Convert Markdown files into PDF. Each Markdown file is converted to HTML in PHP with
[league/commonmark](https://commonmark.thephpleague.com/) (`GithubFlavoredMarkdownConverter`: tables, task lists,
strikethrough, autolinks) and injected into a wrapper (Twig, HTML file or raw HTML), which Chrome then renders.

## The `toHTML` directive

Like Gotenberg, the wrapper references each Markdown file with a `toHTML` directive:

```
{{ toHTML "content.md" }}
```

- The name is either the file's **base name** (`content.md`) or the exact path given to `files()`
  (`docs/content.md`). If two files passed to `files()` share a base name, reference them by path.
- Spacing and quotes are flexible: `{{toHTML "content.md"}}`, `{{ toHTML 'content.md' }}`,
  `{{  toHTML   "content.md"  }}` and HTML-escaped quotes (`{{ toHTML &quot;content.md&quot; }}`) all work.
- A directive that names a file not passed to `files()` (or downloaded with `downloadFrom()`) throws a
  `PartRenderingException` that lists the available files.
- A file passed to `files()` but never referenced is ignored (a debug message is logged).

## Basic usage

### HTML wrapper

```html
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>My PDF</title>
    </head>
    <body>
        {{ toHTML "content.md" }}
    </body>
</html>
```

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

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

### Twig wrapper

> [!WARNING]
> `{{ toHTML "content.md" }}` is **not valid Twig**. If you write it as-is, Twig throws a syntax error. Wrap the
> directive in a [`{% verbatim %}`](https://twig.symfony.com/doc/3.x/tags/verbatim.html) block so that Twig outputs it
> unchanged, and the bundle replaces it afterwards. Printing it as a string also works:
> `{{ '{{ toHTML "content.md" }}' }}`. This is a difference from GotenbergBundle, where the wrapper is sent to
> Gotenberg and the directive is resolved there.

```twig
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ title }}</title>
    </head>
    <body>
        {% verbatim %}
            {{ toHTML "content.md" }}
        {% endverbatim %}
    </body>
</html>
```

```php
return $chromePdf->markdown()
    ->wrapper('wrapper.html.twig', ['title' => 'My document'])
    ->files('content.md')
    ->generate()
    ->stream()
;
```

## Available methods

The methods from [HtmlPdfBuilder](./HtmlPdfBuilder.md) are available (page properties, header/footer, assets,
waiting, emulation, cookies, error handling, [post-processing](post-processing.md) except `embedFiles()`, webhooks
and `generateAsync()`), **except** `content()` / `contentFile()` / `contentRaw()`, which are
replaced by:

| Method | Description |
|---|---|
| `files(Stringable\|string ...$paths)` | Markdown files to convert (`.md`), resolved from `assets_directory` |
| `wrapper(string $template, array $context = [])` | Twig wrapper template |
| `wrapperFile(string $path)` | HTML wrapper file |
| `wrapperRaw(string $html)` | Raw HTML wrapper string |

At least one Markdown file is required, from `files()` or from `downloadFrom()` (downloaded `.md` files are treated
as Markdown files, other files as assets).

## Examples

### Several Markdown files with styling

```html
{# styled_wrapper.html.twig #}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ title }}</title>
    <link rel="stylesheet" href="{{ chrome_pdf_asset('css/doc.css') }}">
</head>
<body>
{% verbatim %}
    <section>{{ toHTML "chapter1.md" }}</section>
    <section style="break-before: page">{{ toHTML "chapter2.md" }}</section>
{% endverbatim %}
</body>
</html>
```

```php
use Daif\ChromePdfBundle\Enumeration\PaperSize;

$chromePdf->markdown()
    ->wrapper('styled_wrapper.html.twig', ['title' => 'Documentation'])
    ->files('chapter1.md', 'chapter2.md')
    ->paperStandardSize(PaperSize::A4)
    ->printBackground()
    ->generateDocumentOutline()
    ->generate()
    ->stream()
;
```

## Notes

- Markdown may contain raw HTML. With untrusted Markdown, read [security](../security.md#untrusted-html-and-markdown).
- Images referenced in Markdown (`![logo](logo.png)`) must be added with `assets()` so they are copied next to the
  rendered HTML.
