# Header and Footer

You can add a header or footer to your generated PDFs.

> [!WARNING]
> Every header or footer template needs to be a full HTML document:
> ```html
><!DOCTYPE html>
><html lang="en">
>  <head>
>    <meta charset="utf-8" />
>    <title>My PDF</title>
>  </head>
>  <body>
>    <!-- Your code goes here -->
>  </body>
></html>
> ```

> [!TIP]
> When using header or footer, remember to add margins to your content. Otherwise your header/footer will overlap with the content. By default, the content occupies all available space.

> [!TIP]
> Chromium provides special CSS classes for page numbers in headers and footers:
> ```html
> Page <span class="pageNumber"></span> / <span class="totalPages"></span>
> ```

## Twig file

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf
            ->html()
            ->content('twig_simple_pdf.html.twig', [
                'my_var' => 'value'
            ])
            ->header('header.html.twig', [
                'my_var' => 'value'
            ])
            ->footer('footer.html.twig', [
                'my_var' => 'value'
            ])
            ->generate()
            ->stream()
        ;
    }
}
```

## HTML file

> [!WARNING]
> By default, HTML files are fetched from the assets folder of your application.
> For more information about path resolution go to [assets documentation](../assets.md).

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf
            ->html()
            ->headerFile('header.html')
            ->contentFile('content.html')
            ->footerFile('footer.html')
            ->generate()
            ->stream()
        ;
    }
}
```

Relative paths work as well.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf
            ->html()
            ->headerFile('../templates/html/header.html')
            ->contentFile('../templates/html/content.html')
            ->footerFile('../templates/html/footer.html')
            ->generate()
            ->stream()
        ;
    }
}
```
