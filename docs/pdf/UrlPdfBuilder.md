# UrlPdfBuilder

Generate a PDF from a URL.

## Basic usage

### From a URL

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

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
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->url()
            ->route('home', [
                'my_var' => 'value'
            ])
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
| `url(string $url)` | URL of the page to convert |
| `route(string $name, array $parameters)` | Symfony route to convert |

## Examples

### PDF from external URL with cookies

```php
$chromePdf->url()
    ->url('https://example.com/dashboard')
    ->cookies([
        ['name' => 'session', 'value' => 'abc123', 'domain' => 'example.com'],
    ])
    ->generate()
    ->stream()
;
```

### PDF from internal route with custom headers

```php
$chromePdf->url()
    ->route('report_page', ['id' => 42])
    ->extraHttpHeaders(['Authorization' => 'Bearer token'])
    ->paperStandardSize(PaperSize::A4)
    ->landscape()
    ->generate()
    ->stream()
;
```
