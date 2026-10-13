<?php

namespace Daif\ChromePdfBundle\Builder;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\WorkingDirectory;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\Builder\Util\CookieNormalizer;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Builder\Util\ZipArchiver;
use Daif\ChromePdfBundle\Builder\ValueObject\FilePart;
use Daif\ChromePdfBundle\Builder\ValueObject\RenderedPart;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\PdfPostProcessor;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Enumeration\Unit;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\Exception\RuntimeException;
use Daif\ChromePdfBundle\Formatter\PathHelper;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Daif\ChromePdfBundle\Processor\NullProcessor;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @template-covariant TProcessorResult of mixed = null
 */
abstract class AbstractBuilder implements BuilderAsyncInterface, BuilderFileInterface, ServiceSubscriberInterface
{
    use LoggerAwareTrait;
    use ServiceMethodsSubscriberTrait;

    protected ContainerInterface $container;

    private readonly BodyBag $bodyBag;

    private string $headerDisposition = HeaderUtils::DISPOSITION_INLINE;
    private string|null $outputFileName = null;

    private ProcessorInterface $processor;

    public function __construct()
    {
        $this->bodyBag = new BodyBag();
        $this->processor = new NullProcessor();
    }

    /**
     * Execute the browser operation and return the raw binary data.
     *
     * Only called by the default produce() implementation: builders that do not use Chrome override produce() instead.
     *
     * @return string Raw binary data (PDF or screenshot)
     */
    protected function executeBrowser(BrowserInterface $browser): string
    {
        throw new LogicException(\sprintf('"%s" must implement either produce() or executeBrowser().', static::class));
    }

    /**
     * Produces the raw files of the generation into the given per-generation directory, before any post-processing.
     *
     * @return list<string> absolute paths of the produced files
     */
    protected function produce(string $workDir): array
    {
        $target = $workDir.\DIRECTORY_SEPARATOR.'document.'.$this->getFileExtension();
        if (false === @file_put_contents($target, $this->executeBrowser($this->getBrowser()))) {
            throw new RuntimeException(\sprintf('Could not write the generated file "%s".', $target));
        }

        return [$target];
    }

    /**
     * @param HeaderUtils::DISPOSITION_* $headerDisposition
     */
    public function fileName(string $fileName, string $headerDisposition = HeaderUtils::DISPOSITION_INLINE): static
    {
        $this->headerDisposition = $headerDisposition;
        $this->outputFileName = $fileName;

        return $this;
    }

    /**
     * @template TNewProcessorResult of mixed = mixed
     *
     * @param ProcessorInterface<TNewProcessorResult> $processor
     *
     * @phpstan-assert ProcessorInterface<TNewProcessorResult> $this->processor
     *
     * @phpstan-this-out self<TNewProcessorResult>
     */
    public function processor(ProcessorInterface $processor): static
    {
        $this->processor = $processor;

        return $this;
    }

    /**
     * Generates the file. When several files are produced (split without unify, office files without merge),
     * they are returned as a single ZIP archive, like Gotenberg does.
     *
     * @return ChromePdfFileResult<TProcessorResult>
     */
    public function generate(): ChromePdfFileResult
    {
        $this->validatePayloadBody();

        $workingDirectory = WorkingDirectory::create($this->getTempDir());
        $cleanup = null;
        try {
            $cleanup = $this->downloadRemoteFiles();
            $workDir = $workingDirectory->getPath();

            $files = $this->produce($workDir);

            $postProcessOptions = $this->collectPostProcessOptions();
            if ([] !== $postProcessOptions) {
                $postProcessor = $this->getPostProcessor();
                $processed = [];
                foreach ($files as $index => $file) {
                    $fileWorkDir = $workDir.\DIRECTORY_SEPARATOR.'post_'.$index;
                    if (!@mkdir($fileWorkDir, 0o700) && !is_dir($fileWorkDir)) {
                        throw new RuntimeException(\sprintf('Could not create the directory "%s".', $fileWorkDir));
                    }

                    array_push($processed, ...$postProcessor->process($file, $postProcessOptions, $fileWorkDir));
                }
                $files = $processed;
            }

            if ([] === $files) {
                throw new RuntimeException(\sprintf('"%s" did not produce any file.', static::class));
            }

            if (1 === \count($files)) {
                $data = self::readFile($files[0]);
                $contentType = $this->getContentType();
                $fileName = $this->outputFileName;
            } else {
                $archive = $workDir.\DIRECTORY_SEPARATOR.'documents.zip';
                ZipArchiver::create($files, $archive);
                $data = self::readFile($archive);
                $contentType = 'application/zip';
                $fileName = self::zipFileName($this->outputFileName);
            }
        } finally {
            if (null !== $cleanup) {
                $cleanup();
            }
            $workingDirectory->remove($this->getLogger());
        }

        return new ChromePdfFileResult(
            $data,
            $this->processor,
            $this->headerDisposition,
            $fileName,
            $contentType,
        );
    }

    /**
     * Generates the file in a Messenger worker, which then sends it to the webhook URL (see WebhookTrait).
     * Requires symfony/messenger, symfony/http-client and daif_chrome_pdf.async.enabled: true. The processor set with processor() is not carried
     * over to the worker.
     *
     * @throws LogicException                if async generation is disabled
     * @throws MissingRequiredFieldException if no webhook URL is configured
     */
    public function generateAsync(): void
    {
        $asyncGenerator = $this->getAsyncGenerator();

        $this->validatePayloadBody();

        if (!\is_string($this->bodyBag->get('webhookUrl')) || '' === $this->bodyBag->get('webhookUrl')) {
            throw new MissingRequiredFieldException('A webhook URL is required to generate asynchronously: call webhookUrl(), webhookRoute(), webhook() or webhookConfiguration().');
        }

        $asyncGenerator->dispatch(static::class, $this->bodyBag->all(), $this->outputFileName, $this->headerDisposition);
    }

    /**
     * Restores the state of a builder dispatched by generateAsync().
     *
     * @internal
     *
     * @param array<string, mixed> $bodyBagData
     */
    public function restoreState(array $bodyBagData, string|null $fileName, string $disposition): static
    {
        foreach (array_keys($this->bodyBag->all()) as $name) {
            $this->bodyBag->unset($name);
        }

        foreach ($bodyBagData as $name => $value) {
            $this->bodyBag->set($name, $value);
        }

        $this->outputFileName = $fileName;
        $this->headerDisposition = $disposition;

        return $this;
    }

    public function getBodyBag(): BodyBag
    {
        return $this->bodyBag;
    }

    protected function validatePayloadBody(): void
    {
    }

    /**
     * MIME type of the generated file, used for the Content-Type of the response.
     */
    protected function getContentType(): string
    {
        return 'application/pdf';
    }

    /**
     * Extension of the file written by the default produce() implementation.
     */
    protected function getFileExtension(): string
    {
        return 'pdf';
    }

    /**
     * Hook run right before the browser is called (see DownloadFromTrait). The returned closure, if any,
     * is called once the generation is over, even on failure.
     */
    protected function downloadRemoteFiles(): \Closure|null
    {
        return null;
    }

    #[SubscribedService('daif_chrome_pdf.browser')]
    protected function getBrowser(): BrowserInterface
    {
        return $this->container->get('daif_chrome_pdf.browser');
    }

    #[SubscribedService('daif_chrome_pdf.engine.post_processor', nullable: true)]
    protected function getPostProcessor(): PdfPostProcessor
    {
        return $this->getEngineService('daif_chrome_pdf.engine.post_processor', PdfPostProcessor::class);
    }

    #[SubscribedService('daif_chrome_pdf.engine.qpdf', nullable: true)]
    protected function getQpdf(): Qpdf
    {
        return $this->getEngineService('daif_chrome_pdf.engine.qpdf', Qpdf::class);
    }

    #[SubscribedService('daif_chrome_pdf.engine.exiftool', nullable: true)]
    protected function getExiftool(): Exiftool
    {
        return $this->getEngineService('daif_chrome_pdf.engine.exiftool', Exiftool::class);
    }

    #[SubscribedService('daif_chrome_pdf.engine.libreoffice', nullable: true)]
    protected function getLibreOffice(): LibreOffice
    {
        return $this->getEngineService('daif_chrome_pdf.engine.libreoffice', LibreOffice::class);
    }

    #[SubscribedService('daif_chrome_pdf.async_generator', nullable: true)]
    protected function getAsyncGenerator(): AsyncGenerator
    {
        if (
            !$this->container->has('daif_chrome_pdf.async_generator')
            || !($generator = $this->container->get('daif_chrome_pdf.async_generator')) instanceof AsyncGenerator
        ) {
            throw new LogicException('Async generation is disabled. Install symfony/messenger and symfony/http-client and set daif_chrome_pdf.async.enabled: true.');
        }

        return $generator;
    }

    /**
     * Collects the options of the post-processing pipeline (see PdfPostProcessor) from the body bag.
     * An empty array means no post-processing.
     *
     * @return array<string, mixed>
     */
    protected function collectPostProcessOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        $splitMode = $bag->get('splitMode');
        if ($splitMode instanceof \BackedEnum) {
            $options['split'] = [
                'mode' => (string) $splitMode->value,
                'span' => (string) $bag->get('splitSpan', ''),
                'unify' => true === $bag->get('splitUnify'),
            ];
        }

        $pdfFormat = $bag->get('pdfa');
        if ($pdfFormat instanceof \BackedEnum || true === $bag->get('pdfua')) {
            $options['pdfFormat'] = $pdfFormat instanceof \BackedEnum ? (string) $pdfFormat->value : null;
            $options['pdfUniversalAccess'] = true === $bag->get('pdfua');
        }

        $metadata = $bag->get('metadata');
        if (\is_array($metadata) && [] !== $metadata) {
            $options['metadata'] = $metadata;
        }

        if (true === $bag->get('flatten')) {
            $options['flatten'] = true;
        }

        $rotateAngle = $bag->get('rotateAngle');
        if ($rotateAngle instanceof \BackedEnum) {
            $options['rotate'] = [
                'angle' => (int) $rotateAngle->value,
                'pages' => $bag->get('rotatePages'),
            ];
        }

        foreach (['watermark', 'stamp'] as $type) {
            $source = $bag->get($type.'Source');
            if (!$source instanceof \BackedEnum) {
                continue;
            }

            // An image or PDF source is read from the file given to *File(), else from the expression.
            $file = $bag->get($type);
            $expression = \is_array($file) && [] !== $file ? (string) reset($file) : (string) $bag->get($type.'Expression', '');

            $options[$type] = [
                'source' => (string) $source->value,
                'expression' => $expression,
                'pages' => $bag->get($type.'Pages'),
                'options' => $bag->get($type.'Options', []),
            ];
        }

        $bookmarks = $bag->get('bookmarks');
        if (\is_array($bookmarks) && [] !== $bookmarks && array_is_list($bookmarks)) {
            $options['bookmarks'] = $bookmarks;
        }

        $embeds = $bag->get('embeds');
        if (\is_array($embeds) && [] !== $embeds) {
            $options['embeds'] = array_values(array_map('strval', $embeds));
        }

        $userPassword = $bag->get('userPassword');
        $ownerPassword = $bag->get('ownerPassword');
        if (null !== $userPassword || null !== $ownerPassword) {
            $options['encrypt'] = [
                'userPassword' => $userPassword,
                'ownerPassword' => $ownerPassword,
            ];
        }

        return $options;
    }

    /**
     * Copies the files given to files() into the working directory, in the given order.
     *
     * @return list<string>
     */
    protected function copyInputFiles(string $workDir): array
    {
        /** @var array<string, string> $files */
        $files = $this->getBodyBag()->get('files', []);

        $copies = [];
        foreach ($files as $path) {
            $directory = $workDir.\DIRECTORY_SEPARATOR.'input_'.\count($copies);
            if (!@mkdir($directory, 0o700) && !is_dir($directory)) {
                throw new RuntimeException(\sprintf('Could not create the directory "%s".', $directory));
            }

            // Each copy keeps its base name (it names the files of a ZIP result) in its own directory.
            $target = $directory.\DIRECTORY_SEPARATOR.PathHelper::basename($path);
            if (!@copy($path, $target)) {
                throw new RuntimeException(\sprintf('Could not read the file "%s".', $path));
            }

            $copies[] = $target;
        }

        return $copies;
    }

    /**
     * Collects the page options (see BrowserInterface) from the body bag.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidBuilderConfiguration
     */
    protected function collectPageOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        $cookies = $bag->get('cookies');
        if (\is_array($cookies) && [] !== $cookies) {
            $options['cookies'] = array_values(array_map(
                static fn (mixed $cookie): array => CookieNormalizer::normalize($cookie instanceof Cookie || \is_array($cookie) ? $cookie : throw new InvalidBuilderConfiguration(\sprintf('Invalid cookie of type "%s".', get_debug_type($cookie)))),
                $cookies,
            ));
        }

        foreach (['extraHttpHeaders', 'emulatedMediaFeatures', 'failOnHttpStatusCodes', 'failOnResourceHttpStatusCodes', 'ignoreResourceHttpStatusDomains'] as $key) {
            $value = $bag->get($key);
            if (\is_array($value) && [] !== $value) {
                $options[$key] = $value;
            }
        }

        foreach (['userAgent', 'waitDelay', 'waitForExpression', 'waitForSelector'] as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = (string) $value;
            }
        }

        $mediaType = $bag->get('emulatedMediaType');
        if (null !== $mediaType) {
            $options['emulatedMediaType'] = $mediaType instanceof \BackedEnum ? $mediaType->value : (string) $mediaType;
        }

        $options['waitEvent'] = match (true) {
            false === $bag->get('skipNetworkIdleEvent') => 'networkIdle',
            false === $bag->get('skipNetworkAlmostIdleEvent') => 'networkAlmostIdle',
            default => 'load',
        };

        foreach (['omitBackground', 'failOnResourceLoadingFailed', 'failOnConsoleExceptions'] as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = (bool) $value;
            }
        }

        $assets = $bag->get('assets');
        if (\is_array($assets) && [] !== $assets) {
            // Assets are stored as paths so the body bag stays serializable, the browser expects SplFileInfo.
            $options['assets'] = array_map(
                static fn (mixed $asset): \SplFileInfo => $asset instanceof \SplFileInfo ? $asset : new \SplFileInfo((string) $asset),
                $assets,
            );
        }

        return $options;
    }

    /**
     * Collects the Page.printToPDF options (lengths converted into inches) from the body bag.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidBuilderConfiguration
     */
    protected function collectPdfOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        foreach (['paperWidth', 'paperHeight', 'marginTop', 'marginBottom', 'marginLeft', 'marginRight'] as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = self::toInches($key, $value);
            }
        }

        $scale = $bag->get('scale');
        if (null !== $scale) {
            if (!is_numeric($scale)) {
                throw new InvalidBuilderConfiguration(\sprintf('Invalid "scale" value "%s", expected a number between 0.1 and 2.', get_debug_type($scale)));
            }
            $options['scale'] = (float) $scale;
        }

        foreach (['landscape' => 'landscape', 'printBackground' => 'printBackground', 'preferCssPageSize' => 'preferCSSPageSize', 'singlePage' => 'singlePage', 'generateDocumentOutline' => 'generateDocumentOutline', 'generateTaggedPdf' => 'generateTaggedPdf'] as $key => $cdpKey) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$cdpKey] = (bool) $value;
            }
        }

        $pageRanges = $bag->get('nativePageRanges');
        if (\is_string($pageRanges) && '' !== $pageRanges) {
            $options['pageRanges'] = $pageRanges;
        }

        $header = $bag->get(Part::Header->value);
        $footer = $bag->get(Part::Footer->value);
        if (null !== $header || null !== $footer) {
            // Chrome prints its default date/title header (or url/page footer) when only one template is given.
            $options['headerTemplate'] = null !== $header ? $this->extractHtmlContent($header) : '<span></span>';
            $options['footerTemplate'] = null !== $footer ? $this->extractHtmlContent($footer) : '<span></span>';
        }

        return $options;
    }

    /**
     * Collects the screenshot options from the body bag.
     *
     * @return array<string, mixed>
     */
    protected function collectScreenshotOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        foreach (['width', 'height'] as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = (int) $value;
            }
        }

        $deviceScaleFactor = $bag->get('deviceScaleFactor');
        if (null !== $deviceScaleFactor) {
            $options['deviceScaleFactor'] = (float) $deviceScaleFactor;
        }

        foreach (['clip', 'optimizeForSpeed'] as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = (bool) $value;
            }
        }

        $format = $bag->get('format');
        $format = $format instanceof \BackedEnum ? $format->value : $format;
        if (null !== $format) {
            $options['format'] = (string) $format;
        }

        $quality = $bag->get('quality');
        if (null !== $quality && 'png' !== ($options['format'] ?? 'png')) {
            $options['quality'] = (int) $quality;
        }

        return $options;
    }

    /**
     * Extracts the HTML content of a part (RenderedPart or file).
     *
     * @throws PartRenderingException if the file cannot be read
     */
    protected function extractHtmlContent(mixed $part): string
    {
        if ($part instanceof RenderedPart) {
            return $part->body;
        }

        if ($part instanceof FilePart || $part instanceof \SplFileInfo) {
            $path = $part instanceof FilePart ? $part->path : $part->getPathname();
            $content = @file_get_contents($path);
            if (false === $content) {
                throw new PartRenderingException(\sprintf('Could not read the file "%s".', $path));
            }

            return $content;
        }

        if (\is_string($part) || $part instanceof \Stringable) {
            return (string) $part;
        }

        throw new PartRenderingException(\sprintf('Unexpected part of type "%s".', get_debug_type($part)));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function getEngineService(string $id, string $class): object
    {
        if (!$this->container->has($id) || !($service = $this->container->get($id)) instanceof $class) {
            throw new LogicException(\sprintf('The "%s" service is required by "%s". Make sure the DaifChromePdfBundle is registered and symfony/process is installed.', $id, static::class));
        }

        return $service;
    }

    #[SubscribedService('.daif_chrome_pdf.download_guard', nullable: true)]
    private function getTempDirProvider(): DownloadGuard|null
    {
        if (!$this->container->has('.daif_chrome_pdf.download_guard')) {
            return null;
        }

        $guard = $this->container->get('.daif_chrome_pdf.download_guard');

        return $guard instanceof DownloadGuard ? $guard : null;
    }

    private function getTempDir(): string|null
    {
        return $this->getTempDirProvider()?->getTempDir();
    }

    private static function readFile(string $path): string
    {
        $data = @file_get_contents($path);
        if (false === $data) {
            throw new RuntimeException(\sprintf('Could not read the generated file "%s".', $path));
        }

        return $data;
    }

    private static function zipFileName(string|null $fileName): string
    {
        if (null === $fileName || '' === $fileName) {
            return 'documents.zip';
        }

        $extension = PathHelper::extension($fileName);

        return ('' !== $extension ? substr($fileName, 0, -\strlen($extension) - 1) : $fileName).'.zip';
    }

    /**
     * @throws InvalidBuilderConfiguration
     */
    private static function toInches(string $key, mixed $value): float
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid "%s" value of type "%s".', $key, get_debug_type($value)));
        }

        try {
            return Unit::parseToInches($value);
        } catch (InvalidBuilderConfiguration $e) {
            throw new InvalidBuilderConfiguration(\sprintf('Invalid "%s" value: %s', $key, $e->getMessage()), previous: $e);
        }
    }
}
