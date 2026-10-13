<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\StampTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Stamp PDF files. Requires qpdf (text and image stamps are rendered with Chrome).
 *
 * @methodDoc files Add PDF files to stamp.
 * As assets files, by default the PDF files are fetch in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'stamp')]
final class StampPdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use FilesTrait;
    use StampTrait;
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

        if ($this->getBodyBag()->get('stampSource') === null) {
            throw new MissingRequiredFieldException('Field "stampSource" must be provided.');
        }

        if ($this->getBodyBag()->get('stampExpression') === null) {
            throw new MissingRequiredFieldException('Field "stampExpression" must be provided.');
        }

        $source = $this->getBodyBag()->get('stampSource');
        if (\in_array($source, [StampSource::Image, StampSource::Pdf], true) && $this->getBodyBag()->get('stamp') === null) {
            throw new MissingRequiredFieldException('A stamp file is required when source is "image" or "pdf".');
        }
    }
}
