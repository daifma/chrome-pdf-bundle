# ChromePdfBundle

### *WYSIWYPDF* -- What You See Is What You PDF

> The dockerless PDF generator for Symfony. Just Chrome. No containers, no wrapping, no kidding.

<div align="center">
    <pre>composer require daif/chrome-pdf-bundle</pre>
</div>

## Why this bundle?

I wanted to use [sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle) -- it's a great and complete Symfony bundle for PDF generation. But it relies on [Gotenberg](https://gotenberg.dev/), which requires a running **Docker container**.

In my case, working in **on-premise environments** (banking, insurance, regulated industries), Docker is simply not available on production servers. Security policies and infrastructure constraints prevent running containers.

Yet these same machines almost always have a **browser installed**, or can easily add one. Google Chrome and Chromium are well-maintained, widely trusted, and available on virtually every Linux distribution through standard package managers.

So I built **ChromePdfBundle**: the same clean builder-based API, but driving Chrome/Chromium **directly** via the Chrome DevTools Protocol -- no Docker, no external service, no extra infrastructure.

| Docker-based solutions | ChromePdfBundle |
|---|---|
| Require Docker + a running container | Requires only a Chrome/Chromium binary |
| HTTP calls to an external service | Direct communication via CDP |
| Extra infrastructure to maintain | Uses a browser already on the system |
| Not usable in Docker-free environments | Works everywhere Chrome runs |

## How to install

### Requirements

- PHP 8.1+
- Symfony 6.4 / 7.x / 8.x
- Google Chrome or Chromium installed on the system (see [Chrome installation guide](./docs/chrome-installation.md))

```bash
composer require daif/chrome-pdf-bundle
```

This installs the bundle along with [chrome-php/chrome](https://github.com/chrome-php/chrome), the PHP library used to communicate with Chrome via the DevTools Protocol.

### Enable the bundle

If not using Symfony Flex, manually register the bundle:

```php
// config/bundles.php

return [
    // ...
    Daif\ChromePdfBundle\DaifChromePdfBundle::class => ['all' => true],
];
```

### Configuration

Create a minimal configuration:

```yaml
# config/packages/daif_chrome_pdf.yaml

daif_chrome_pdf:
    assets_directory: '%kernel.project_dir%/assets'
```

The bundle will automatically detect Chrome/Chromium on your system. You can also specify the binary path explicitly:

```yaml
daif_chrome_pdf:
    chrome_binary: '/usr/bin/google-chrome'
```

## Basic Usage

### PDF from Twig template

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController
{
    public function generateInvoice(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->content('invoice.html.twig', [
                'invoice' => $invoice,
            ])
            ->generate()
            ->stream()
        ;
    }
}
```

### PDF from URL

```php
use Daif\ChromePdfBundle\ChromePdfInterface;

class ReportController
{
    public function generateReport(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->url()
            ->url('https://example.com/report')
            ->generate()
            ->stream()
        ;
    }
}
```

### PDF from Markdown

```php
use Daif\ChromePdfBundle\ChromePdfInterface;

class DocController
{
    public function generateDoc(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->markdown()
            ->wrapper('wrapper.html.twig')
            ->files('content.md')
            ->generate()
            ->stream()
        ;
    }
}
```

### Screenshot

```php
use Daif\ChromePdfBundle\ChromeScreenshotInterface;

class ScreenshotController
{
    public function capture(ChromeScreenshotInterface $chromeScreenshot): Response
    {
        return $chromeScreenshot->html()
            ->content('page.html.twig')
            ->generate()
            ->stream()
        ;
    }
}
```

### Twig assets

If a template needs to link to a static asset (image, CSS, font), use the `{{ chrome_pdf_asset() }}` Twig function:

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>My PDF</title>
</head>
<body>
    <img src="{{ chrome_pdf_asset('img/logo.png') }}" alt="Logo"/>
</body>
</html>
```

## Advanced Usage

1. [Chrome installation guide](./docs/chrome-installation.md)
2. [Configuration](./docs/configuration.md)
3. [Processing (saving, streaming, S3...)](./docs/processing.md)
4. [Working with assets](./docs/assets.md)
5. [Working with fonts](./docs/fonts.md)

### PDF

1. [HTML Builder](./docs/pdf/HtmlPdfBuilder.md)
2. [URL Builder](./docs/pdf/UrlPdfBuilder.md)
3. [Markdown Builder](./docs/pdf/MarkdownPdfBuilder.md)
4. [Header / Footer](./docs/pdf/header-footer.md)

### Screenshot

1. [HTML Builder](./docs/screenshot/HtmlScreenshotBuilder.md)
2. [URL Builder](./docs/screenshot/UrlScreenshotBuilder.md)
3. [Markdown Builder](./docs/screenshot/MarkdownScreenshotBuilder.md)

## Licence

MIT License (MIT): see the [License File](LICENSE) for more details.
