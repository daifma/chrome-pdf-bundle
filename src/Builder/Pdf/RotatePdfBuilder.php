<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\RotateTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * You may have the possibility to rotate PDF pages by 90°, 180°, or 270°. Requires qpdf.
 *
 * @methodDoc files Add PDF files to rotate.
 * As assets files, by default the PDF files are fetched in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf','document_2.pdf')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'rotate')]
final class RotatePdfBuilder extends AbstractBuilder
{
    use DownloadFromTrait;
    use FilesTrait;
    use RotateTrait;
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

        if ($this->getBodyBag()->get('rotateAngle') === null) {
            throw new MissingRequiredFieldException('Field "rotateAngle" must be provided.');
        }
    }
}
