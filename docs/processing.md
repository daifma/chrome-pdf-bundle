# Processing

To save the PDF or Screenshot as a file, you need a `Daif\ChromePdfBundle\Processor\ProcessorInterface`.

## Native Processors

Given an example for a PDF (works the same for Screenshots):

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;

/** @var ChromePdfInterface $chromePdf */
$chromePdf = /* ... */;

/** @var ChromePdfFileResult $result */
$result = $chromePdf->html()
    // ...
    ->fileName('my_pdf')
    ->processor(/* ... */)
    ->generate()
;

// Either process it with
$result->process(); // return type depends on the Processor used (see below)
// or send a response to the browser:
$result->stream(); // returns a Symfony\Component\HttpFoundation\StreamedResponse
```

Here is the list of existing Processors:

### `Daif\ChromePdfBundle\Processor\FileProcessor`

Useful if you want to store the file in the local filesystem.
<details>
<summary>Example in a controller</summary>

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

#[Route(path: '/my-pdf', name: 'my_pdf')]
public function pdf(
    ChromePdfInterface $chromePdf,
    Filesystem $filesystem,

    #[Autowire('%kernel.project_dir%/var/pdf')]
    string $pdfStorage,
): Response {
    return $chromePdf->html()
        // ...
        ->fileName('my_pdf')
        ->processor(new FileProcessor(
            $filesystem,
            $pdfStorage,
        ))
        ->generate()
        ->stream()
    ;
}
```

This will save the file under `%kernel.project_dir%/var/pdf/my_pdf.pdf` once the file has been fully streamed to the browser.

</details>

<details>
<summary>If you are not streaming to a browser, you can still process the file using the `process` method instead of `stream`</summary>

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Symfony\Component\Filesystem\Filesystem;

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,

        #[Autowire('%kernel.project_dir%/var/pdf')]
        private readonly string $pdfStorage,
    ) {}

    public function pdf(): \SplFileInfo
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new FileProcessor(
                new Filesystem(),
                $this->pdfStorage,
            ))
            ->generate()
            ->process()
        ;
    }
}
```

This will return a `SplFileInfo` of the generated file stored at `%kernel.project_dir%/var/pdf/my_pdf.pdf`.

</details>

### `Daif\ChromePdfBundle\Processor\NullProcessor`

Empty processor. Does nothing. Returns `null`.

### `Daif\ChromePdfBundle\Processor\TempfileProcessor`

Creates a temporary file and dumps all chunks into it. Returns a `resource` of said `tmpfile()`.

<details>
<summary>Example in a service</summary>

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\TempfileProcessor;

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,
    ) {}

    /**
     * @return resource
     */
    public function pdf(): mixed
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new TempfileProcessor())
            ->generate()
            ->process()
        ;
    }
}
```

</details>

### `Daif\ChromePdfBundle\Processor\ChainProcessor`

Apply multiple processors. Each chunk will be sent to each processor sequentially. Returns an array of values returned by chained processors.

<details>
<summary>Example in a service</summary>

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\ChainProcessor;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @implements ProcessorInterface<int>
 */
class CustomProcessor implements ProcessorInterface
{
    public function __invoke(string|null $fileName): \Generator { /* ... */ }
}

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,

        #[Autowire('%kernel.project_dir%/var/pdf')]
        private readonly string $pdfStorage,
    ) {}

    /**
     * @return array{0: \SplFileInfo, 1: int}
     */
    public function pdf(): array
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new ChainProcessor([
                new FileProcessor(
                    new Filesystem(),
                    $this->pdfStorage,
                ),
                new CustomProcessor(),
            ]))
            ->generate()
            ->process()
        ;
    }
}
```

</details>

### `Daif\ChromePdfBundle\Bridge\LeagueFlysystem\Processor\FlysystemProcessor`

Upload using the `league/flysystem-bundle` package. Returns a `callable`. This callable will return the uploaded content.

<details>
<summary>Example in a service</summary>

```php
use League\Flysystem\FilesystemOperator;
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Bridge\LeagueFlysystem\Processor\FlysystemProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,

        #[Autowire(service: 'pdfs.storage')]
        private readonly FilesystemOperator $filesystemOperator,
    ) {}

    /**
     * @return Closure(): string
     */
    public function pdf(): Closure
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new FlysystemProcessor(
                $this->filesystemOperator,
            ))
            ->generate()
            ->process()
        ;
    }
}
```

</details>

### `Daif\ChromePdfBundle\Bridge\AsyncAws\Processor\AsyncAwsS3MultiPartProcessor`

Upload using the `async-aws/s3` package. Uploads using the [multipart upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html) feature of S3. Returns a `AsyncAws\S3\Result\CompleteMultipartUploadOutput` object.

<details>
<summary>Example in a service</summary>

```php
use AsyncAws\S3\Result\CompleteMultipartUploadOutput;
use Daif\ChromePdfBundle\Bridge\AsyncAws\Processor\AsyncAwsS3MultiPartProcessor;
use Daif\ChromePdfBundle\ChromePdfInterface;

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,
        private readonly S3Client $s3Client,
    ) {}

    public function pdf(): CompleteMultipartUploadOutput
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new AsyncAwsS3MultiPartProcessor(
                $this->s3Client,
                'bucket-name',
            ))
            ->generate()
            ->process()
        ;
    }
}
```

</details>

### `Daif\ChromePdfBundle\Processor\InMemoryProcessor`

Loads the full PDF in memory. Should **NOT** be used in production.
This is not memory safe and you might end up with a "Fatal Error: Allowed Memory Size".
Consider using one of the other Processors.

<details>
<summary>Example in a service</summary>

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;

class SomeService
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,
    ) {}

    public function pdf(): string
    {
        return $this->chromePdf->html()
            //
            ->fileName('my_pdf')
            ->processor(new InMemoryProcessor())
            ->generate()
            ->process()
        ;
    }
}
```

</details>

## Custom processor

A custom processor must implement `Daif\ChromePdfBundle\Processor\ProcessorInterface` which requires that your `__invoke` method is a `\Generator`. To receive a chunk you must assign `yield` to a variable like so: `$chunk = yield`.

The basic needed code is the following:

```php
use Daif\ChromePdfBundle\Processor\ProcessorInterface;

/**
 * @implements ProcessorInterface<YOUR_GENERATOR_RETURN_TYPE>
 */
class CustomProcessor implements ProcessorInterface
{
    public function __invoke(string|null $fileName): \Generator
    {
        do {
            $chunk = yield;
            // do something with it
        } while (!$chunk->isLast());
        // rest of your code
    }
}
```
