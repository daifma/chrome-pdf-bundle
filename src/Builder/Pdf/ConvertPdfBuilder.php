<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FlattenTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\PdfFormatTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * You may have the possibility to convert several PDF document. Requires LibreOffice.
 *
 * @methodDoc files If you provide multiple PDF files you will get ZIP folder containing all the converted PDF.
 *
 * @example files('document.pdf', __DIR__'/../../public/document_2.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'convert')]
final class ConvertPdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use FilesTrait;
    use FlattenTrait;
    use PdfFormatTrait;
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
        if ($this->getBodyBag()->get('pdfa') === null && $this->getBodyBag()->get('pdfua') === null) {
            throw new MissingRequiredFieldException('At least "pdfa" or "pdfua" must be provided.');
        }

        if ($this->getBodyBag()->get('files') === null && [] === $this->getBodyBag()->get('downloadFrom', [])) {
            throw new MissingRequiredFieldException('At least one PDF file is required.');
        }
    }
}
