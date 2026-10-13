# Working with fonts

You can add fonts in the same way as assets. The `chrome_pdf_font_face(path_to_font, font_family)`
function follows the same path resolution logic as [chrome_pdf_asset()](assets.md),
but it generates a `@font-face` rule that can be used inside a `<style>` block.

|            |         HTML         |        URL         |      Markdown      |
|:----------:|:--------------------:|:------------------:|:------------------:|
|    PDF     |  :white_check_mark:  | :white_check_mark: | :white_check_mark: |
| Screenshot |  :white_check_mark:  | :white_check_mark: | :white_check_mark: |

## Twig file

The `{{ chrome_pdf_font_face() }}` function helps generate an `@font-face`
declaration with the correct asset path.

### Example with "chrome_pdf_font_face"

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF with Custom Font</title>
        <style>
            {{ chrome_pdf_font_face('fonts/custom-font.ttf', 'my_font') }}
            h1 {
                color: red;
                font-family: "my_font";
            }
        </style>
    </head>
    <body>
        <h1>This text uses the custom font.</h1>
    </body>
</html>
```

### Output

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF with Custom Font</title>
        <style>
            @font-face {font-family: "my_font";src: url("custom-font.ttf");}
            h1 {
                color: red;
                font-family: "my_font";
            }
        </style>
    </head>
    <body>
        <h1>This text uses the custom font.</h1>
    </body>
</html>
```

### Example with "chrome_pdf_font_style_tag"

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF with Custom Font</title>
        {{ chrome_pdf_font_style_tag('fonts/custom-font.ttf', 'my_font') }}
        <style>
            h1 {
                color: red;
                font-family: "my_font";
            }
        </style>
    </head>
    <body>
        <h1>This text uses the custom font.</h1>
    </body>
</html>
```

### Output

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF with Custom Font</title>
        <style>@font-face {font-family: "my_font";src: url("custom-font.ttf");}</style>
        <style>
            h1 {
                color: red;
                font-family: "my_font";
            }
        </style>
    </head>
    <body>
        <h1>This text uses the custom font.</h1>
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

If your file is an HTML file (not a Twig template), you can still include
fonts manually.

The only requirement is that their paths in the HTML file must be on the root
level.

```html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>PDF with Custom Font</title>
        <style>
            @font-face {
                font-family: "my_font";
                src: url("custom-font.ttf");
            }
        </style>
    </head>
    <body>
        <p style="font-family: 'my_font';">This text uses the custom font.</p>
    </body>
</html>
```

All you need to do is add the path of the asset file to either
`assets(...string)` or `addAsset(string)`.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->contentFile('content.html')
            ->assets('fonts/my-font.ttf')
            ->generate()
            ->stream()
        ;
    }
}
```

## Fonts in header and footer

Header and footer templates don't inherit the fonts of the main document. Call `chrome_pdf_font_face()` (or
`chrome_pdf_font_style_tag()`) inside the header or footer template itself. The font file is then inlined as a
`data:` URI, because Chrome cannot load files there. Prefer small font subsets (WOFF2) to keep the PDF light.

## System fonts

Fonts that are not embedded with the functions above come from the operating system of the server that runs Chrome:

- **Linux** servers often have very few fonts installed. Text then falls back to another font, or shows up as empty
  squares (tofu), especially for emoji and CJK. Install `fonts-liberation`, `fonts-dejavu-core`, `fonts-noto-core`,
  `fonts-noto-color-emoji` (Debian/Ubuntu) or their RHEL equivalents, then run `fc-cache -f`.
- **Windows** has the common fonts, but fonts installed *for the current user only* are invisible to IIS / FPM service
  accounts. Install them *for all users*.
- For identical output on every machine, embed the fonts with `chrome_pdf_font_face()`.

See [Chrome installation](chrome-installation.md#fonts-on-linux) for the commands.
