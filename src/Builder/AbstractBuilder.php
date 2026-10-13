<?php

namespace Daif\ChromePdfBundle\Builder;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\Builder\Util\CookieNormalizer;
use Daif\ChromePdfBundle\Builder\ValueObject\RenderedPart;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Enumeration\Unit;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
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
abstract class AbstractBuilder implements BuilderFileInterface, ServiceSubscriberInterface
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
     * @return string Raw binary data (PDF or screenshot)
     */
    abstract protected function executeBrowser(BrowserInterface $browser): string;

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
     * @return ChromePdfFileResult<TProcessorResult>
     */
    public function generate(): ChromePdfFileResult
    {
        $this->validatePayloadBody();

        $cleanup = $this->downloadRemoteFiles();
        try {
            $data = $this->executeBrowser($this->getBrowser());
        } finally {
            if (null !== $cleanup) {
                $cleanup();
            }
        }

        return new ChromePdfFileResult(
            $data,
            $this->processor,
            $this->headerDisposition,
            $this->outputFileName,
            $this->getContentType(),
        );
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
            $options['assets'] = $assets;
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

        if ($part instanceof \SplFileInfo) {
            $content = @file_get_contents($part->getPathname());
            if (false === $content) {
                throw new PartRenderingException(\sprintf('Could not read the file "%s".', $part->getPathname()));
            }

            return $content;
        }

        if (\is_string($part) || $part instanceof \Stringable) {
            return (string) $part;
        }

        throw new PartRenderingException(\sprintf('Unexpected part of type "%s".', get_debug_type($part)));
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
