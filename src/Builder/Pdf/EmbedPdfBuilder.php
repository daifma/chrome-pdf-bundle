<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EmbedTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Embeds files into PDF files. Requires qpdf.
 *
 * @methodDoc files Add PDF files which is the source of embedded file.
 * As assets files, by default the PDF files are fetch in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf','document_2.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'embed')]
final class EmbedPdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use EmbedTrait;
    use FilesTrait;
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

        if ($this->getBodyBag()->get('embeds') === null) {
            throw new MissingRequiredFieldException('At least one embed file is required.');
        }
    }
}
