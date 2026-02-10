# Working with assets

You can add assets in several ways, available for most builders.

|            |         HTML         |        URL         |      Markdown      |
|:----------:|:--------------------:|:------------------:|:------------------:|
|    PDF     |  :white_check_mark:  | :white_check_mark: | :white_check_mark: |
| Screenshot |  :white_check_mark:  | :white_check_mark: | :white_check_mark: |

> [!WARNING]
> By default, the assets are fetched from the `assets` folder of your application.
> If your asset files are in another folder, you can override the
> default value of `assets_directory` in your configuration file
> `config/daif_chrome_pdf.yaml`.
>
> The asset path resolution depends on certain criteria:
> - If an absolute path is provided in `{{ chrome_pdf_asset() }}`, `assets()`
> or `addAsset`, this path will be applied and not the one in the configuration file.
>
> - If a path is provided in `{{ chrome_pdf_asset() }}`, `assets()`
> or `addAsset`, it will be treated as a relative path from the `assets_directory` configuration.
>
> - If an absolute path is provided in the configuration file (`assets_directory`), the path applied
> in `{{ chrome_pdf_asset() }}`, `assets()` or `addAsset` will have `assets_directory` as base path.
>
> - If a relative path is provided in the configuration file (`assets_directory`), the path applied
> will have the root of the project as base path followed by the path from the configuration file.
>
> <details>
>     <summary>Examples</summary>
>
> ```php
> // Without daif_chrome_pdf.assets_directory:
> $builder->addAsset('/some/absolute/path/img.png'); // (string) '/some/absolute/path/img.png'
>
> // Without daif_chrome_pdf.assets_directory:
> $builder->addAsset('some/relative/img.png'); // (string) '%kernel.project_dir%/assets/some/relative/img.png'
>
> // With daif_chrome_pdf.assets_directory: '/some/absolute/path'
> $builder->addAsset('./some/relative/img.png'); // (string) '/some/absolute/path/some/relative/img.png'
>
> // With daif_chrome_pdf.assets_directory: 'some/relative/path'
> $builder->addAsset('./also/relative/img.png'); // (string) '%kernel.project_dir%/assets/some/relative/path/also/relative/img.png'
> ```
> </details>

## Twig file

`{{ chrome_pdf_asset() }}` Twig function will help you generate an asset path.
This function works like [asset() Twig function](https://symfony.com/doc/current/templates.html#linking-to-css-javascript-and-image-assets).

You can provide an absolute path.

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF body</title>
    </head>
    <body>
        <img src="{{ chrome_pdf_asset('img/ceo.jpeg') }}" alt="CEO"/>
        <img src="{{ chrome_pdf_asset('img/admin.jpeg') }}" alt="Admin"/>
    </body>
</html>
```

The path provided can be relative as well.

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF body</title>
    </head>
    <body>
        <img src="{{ chrome_pdf_asset('../public/img/ceo.jpeg') }}" alt="CEO"/>
        <img src="{{ chrome_pdf_asset('../public/img/admin.jpeg') }}" alt="Admin"/>
    </body>
</html>
```

And in your controller nothing needs to be changed.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->content('twig_simple_pdf.html.twig')
            ->generate()
            ->stream()
         ;
    }
}
```

## HTML file

If your file is an HTML file and not a Twig template, you can also
add some assets as below.

The only requirement is that their paths in the HTML file are on the root level.

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF body</title>
    </head>
    <body>
        <img src="ceo.jpeg" />
        <img src="admin.jpeg" />
    </body>
</html>
```

All you need to do is add the path of the asset file to either `assets(...string)` or `addAsset(string)`.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->contentFile('content.html')
            ->assets(
                'img/ceo.jpeg',
                'img/admin.jpeg'
            )
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
        return $chromePdf->html()
            ->contentFile('../public/content.html')
            ->assets(
                '../img/ceo.jpeg',
                '../img/admin.jpeg'
            )
            ->generate()
            ->stream()
        ;
    }
}
```

You can also add assets conditionally with `addAsset()`:

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->contentFile('../public/content.html')
            ->assets(
                '../img/ceo.jpeg',
                '../img/admin.jpeg'
            )
            ->addAsset('../img/developer.jpeg')
            ->generate()
            ->stream()
        ;
    }
}
```

In the example above `ceo.jpeg`, `admin.jpeg` and `developer.jpeg` will all be loaded.
