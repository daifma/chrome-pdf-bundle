# Testing

## Creating mock results

The `ChromePdfFileResult` class can be instantiated directly for testing purposes since it accepts raw string data:

```php
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\Processor\NullProcessor;

$result = new ChromePdfFileResult(
    '%PDF-1.4 fake content',
    new NullProcessor(),
    'attachment',
    'test.pdf',
);
```

You can use this in your test doubles:

```php
$invoicePdfGenerator
    ->method('getPdfForInvoice')
    ->with($invoiceId)
    ->willReturn(new ChromePdfFileResult(
        file_get_contents(sprintf('%s/fixtures/test_%d.pdf', __DIR__, $invoiceId)),
        new NullProcessor(),
        'attachment',
        'invoice.pdf',
    ))
;
```

## Running this bundle's own browser tests

The functional tests that drive a real browser are in the `browser` PHPUnit group. The `CHROME_PDF_BROWSER_TESTS`
environment variable controls them (default `auto` in `phpunit.xml`; a variable already set in the environment wins):

| Value | Effect |
|---|---|
| `auto` | Run when a browser is found (`CHROME_PATH` or auto-detection), skip otherwise |
| `1` | A browser is required: the tests fail when none is found (used by the CI) |
| `0` | Always skip |

```shell
CHROME_PDF_BROWSER_TESTS=1 CHROME_PATH=/usr/bin/chromium ./vendor/bin/phpunit
./vendor/bin/phpunit --exclude-group browser     # skip them
```

`CHROME_NO_SANDBOX=0|1` overrides the sandbox setting used by these tests. Without it, the sandbox is disabled on
Linux (containers and CI runners usually lack unprivileged user namespaces) and enabled elsewhere.
