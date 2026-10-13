# EncryptPdfBuilder

Protect PDF files with a password.

Requires [qpdf](../engines.md).

## Basic usage

> [!WARNING]
> Like assets, PDF files are resolved from the `assets_directory` of your application by default.
> For more information about path resolution, see the [assets documentation](../assets.md).

> [!WARNING]
> You must provide at least the user password.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->encrypt()
            ->files('document_1.pdf')
            ->userPassword('MyUserPassword')
            ->ownerPassword('MyOwnerPassword')
            ->generate()
            ->stream()
        ;
    }
}
```

At least one PDF and `userPassword()` are required.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to encrypt (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Encryption

| Method | Description |
|---|---|
| `userPassword(string\|null $userPassword)` | Password needed to open the file |
| `ownerPassword(string\|null $ownerPassword)` | Password that grants full permissions. Defaults to the user password |

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## How it works

The file is encrypted with AES-256 (`qpdf --encrypt <user> <owner> 256`). Passwords are marked
`#[\SensitiveParameter]`: they do not appear in stack traces. They are passed to qpdf through a temporary argument
file in the private working directory (`qpdf @file`), so they do not appear in the process list, and they are masked
in error messages.

On the Chromium, Office, merge and split builders, the same methods encrypt the result as the last
[post-processing](post-processing.md#encryption) step.
