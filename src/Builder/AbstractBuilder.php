<?php

namespace Daif\ChromePdfBundle\Builder;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\Processor\NullProcessor;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use Psr\Container\ContainerInterface;
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

        $data = $this->executeBrowser($this->getBrowser());

        return new ChromePdfFileResult(
            $data,
            $this->processor,
            $this->headerDisposition,
            $this->outputFileName,
        );
    }

    public function getBodyBag(): BodyBag
    {
        return $this->bodyBag;
    }

    protected function validatePayloadBody(): void
    {
    }

    #[SubscribedService('daif_chrome_pdf.browser')]
    protected function getBrowser(): BrowserInterface
    {
        return $this->container->get('daif_chrome_pdf.browser');
    }

    /**
     * Collect page options from the body bag for CDP page configuration.
     *
     * @return array<string, mixed>
     */
    protected function collectPageOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        if (null !== $bag->get('cookies')) {
            $options['cookies'] = $bag->get('cookies');
        }

        if (null !== $bag->get('extraHttpHeaders')) {
            $options['extraHttpHeaders'] = $bag->get('extraHttpHeaders');
        }

        if (null !== $bag->get('userAgent')) {
            $options['userAgent'] = $bag->get('userAgent');
        }

        if (null !== $bag->get('emulatedMediaType')) {
            $mediaType = $bag->get('emulatedMediaType');
            $options['emulatedMediaType'] = $mediaType instanceof \BackedEnum ? $mediaType->value : $mediaType;
        }

        if (null !== $bag->get('waitDelay')) {
            $options['waitDelay'] = $bag->get('waitDelay');
        }

        if (null !== $bag->get('waitForExpression')) {
            $options['waitForExpression'] = $bag->get('waitForExpression');
        }

        if (null !== $bag->get('skipNetworkIdleEvent') && $bag->get('skipNetworkIdleEvent')) {
            $options['waitEvent'] = 'load';
        }

        if (null !== $bag->get('assets')) {
            $options['assets'] = $bag->get('assets');
        }

        return $options;
    }

    /**
     * Collect PDF-specific options from the body bag for CDP Page.printToPDF.
     *
     * @return array<string, mixed>
     */
    protected function collectPdfOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        $floatKeys = ['paperWidth', 'paperHeight', 'marginTop', 'marginBottom', 'marginLeft', 'marginRight', 'scale'];
        foreach ($floatKeys as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $options[$key] = $this->parseUnitToInches($value);
            }
        }

        $boolKeys = ['landscape', 'printBackground', 'preferCssPageSize', 'singlePage', 'omitBackground', 'generateDocumentOutline', 'generateTaggedPdf'];
        foreach ($boolKeys as $key) {
            $value = $bag->get($key);
            if (null !== $value) {
                $cdpKey = match ($key) {
                    'preferCssPageSize' => 'preferCSSPageSize',
                    default => $key,
                };
                $options[$cdpKey] = (bool) $value;
            }
        }

        if (null !== $bag->get('nativePageRanges')) {
            $options['pageRanges'] = $bag->get('nativePageRanges');
        }

        // Header/footer templates
        $headerPart = $bag->get('header.html');
        if (null !== $headerPart) {
            $options['headerTemplate'] = $this->extractHtmlContent($headerPart);
        }

        $footerPart = $bag->get('footer.html');
        if (null !== $footerPart) {
            $options['footerTemplate'] = $this->extractHtmlContent($footerPart);
        }

        return $options;
    }

    /**
     * Collect screenshot-specific options from the body bag.
     *
     * @return array<string, mixed>
     */
    protected function collectScreenshotOptions(): array
    {
        $options = [];
        $bag = $this->getBodyBag();

        if (null !== $bag->get('width')) {
            $options['width'] = (int) $bag->get('width');
        }

        if (null !== $bag->get('height')) {
            $options['height'] = (int) $bag->get('height');
        }

        if (null !== $bag->get('format')) {
            $format = $bag->get('format');
            $options['format'] = $format instanceof \BackedEnum ? $format->value : $format;
        }

        if (null !== $bag->get('quality')) {
            $options['quality'] = (int) $bag->get('quality');
        }

        if (null !== $bag->get('omitBackground') && $bag->get('omitBackground')) {
            $options['captureBeyondViewport'] = false;
        }

        if (null !== $bag->get('optimizeForSpeed')) {
            $options['optimizeForSpeed'] = (bool) $bag->get('optimizeForSpeed');
        }

        if (null !== $bag->get('clip') && $bag->get('clip')) {
            $options['clip'] = [
                'x' => 0,
                'y' => 0,
                'width' => $options['width'] ?? 800,
                'height' => $options['height'] ?? 600,
                'scale' => 1,
            ];
        }

        return $options;
    }

    /**
     * Extract HTML content from a RenderedPart or SplFileInfo.
     */
    protected function extractHtmlContent(mixed $part): string
    {
        if ($part instanceof ValueObject\RenderedPart) {
            return $part->body;
        }

        if ($part instanceof \SplFileInfo) {
            return file_get_contents($part->getPathname()) ?: '';
        }

        return (string) $part;
    }

    /**
     * Parse a value with unit suffix to float inches.
     */
    private function parseUnitToInches(mixed $value): float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        $str = (string) $value;

        if (preg_match('/^([\d.]+)(in|cm|mm|pt|px)?$/', $str, $matches)) {
            $num = (float) $matches[1];
            $unit = $matches[2] ?? 'in';

            return match ($unit) {
                'in' => $num,
                'cm' => $num / 2.54,
                'mm' => $num / 25.4,
                'pt' => $num / 72.0,
                'px' => $num / 96.0,
            };
        }

        return (float) $str;
    }
}
