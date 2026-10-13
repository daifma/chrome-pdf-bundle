<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WatermarkTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Watermark PDF files. Requires qpdf (text and image watermarks are rendered with Chrome).
 *
 * @methodDoc files Add PDF files to watermark.
 * As assets files, by default the PDF files are fetch in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'watermark')]
final class WatermarkPdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use FilesTrait;
    use WatermarkTrait;
    use WebhookTrait;

    private const AVAILABLE_EXTENSIONS = ['pdf'];

    protected function getAllowedFilesExtensions(): array
    {
        return self::AVAILABLE_EXTENSIONS;
    }

    protected function produce(string $workDir): array
    {
        return $this->copyInputFiles($workDir);
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get('files') === null && [] === $this->getBodyBag()->get('downloadFrom', [])) {
            throw new MissingRequiredFieldException('At least one PDF file is required.');
        }

        if ($this->getBodyBag()->get('watermarkSource') === null) {
            throw new MissingRequiredFieldException('Field "watermarkSource" must be provided.');
        }

        if ($this->getBodyBag()->get('watermarkExpression') === null) {
            throw new MissingRequiredFieldException('Field "watermarkExpression" must be provided.');
        }

        $source = $this->getBodyBag()->get('watermarkSource');
        if (\in_array($source, [WatermarkSource::Image, WatermarkSource::Pdf], true) && $this->getBodyBag()->get('watermark') === null) {
            throw new MissingRequiredFieldException('A watermark file is required when source is "image" or "pdf".');
        }
    }
}
