<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EmbedTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EncryptTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FlattenTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\MetadataTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\PdfFormatTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\SplitTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\StampTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WatermarkTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * You may have the possibility to split several PDF pages. Requires qpdf.
 * Several resulting files are returned as a ZIP archive.
 *
 * @methodDoc files Add PDF files to split.
 * As assets files, by default the PDF files are fetch in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf','document_2.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'split')]
final class SplitPdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use EmbedTrait;
    use EncryptTrait;
    use FilesTrait;
    use FlattenTrait;
    use MetadataTrait;
    use PdfFormatTrait;
    use SplitTrait;
    use StampTrait;
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

        if ($this->getBodyBag()->get('splitMode') === null) {
            throw new MissingRequiredFieldException('Field "splitMode" must be provided.');
        }

        if ($this->getBodyBag()->get('splitSpan') === null) {
            throw new MissingRequiredFieldException('Field "splitSpan" must be provided.');
        }
    }
}
