<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumPdfTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Convert HTML or Twig files into PDF using Chrome.
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'html')]
final class HtmlPdfBuilder extends AbstractBuilder implements BuilderAssetInterface
{
    use ChromiumPdfTrait;

    protected function executeBrowser(BrowserInterface $browser): string
    {
        $htmlContent = $this->extractHtmlContent($this->getBodyBag()->get(Part::Body->value));

        return $browser->htmlToPdf(
            $htmlContent,
            $this->collectPdfOptions(),
            $this->collectPageOptions(),
        );
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get(Part::Body->value) === null) {
            throw new MissingRequiredFieldException('Content is required');
        }
    }
}
