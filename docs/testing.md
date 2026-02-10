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
