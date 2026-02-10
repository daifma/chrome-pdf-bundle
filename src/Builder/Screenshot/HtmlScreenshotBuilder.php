<?php

namespace Daif\ChromePdfBundle\Builder\Screenshot;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumScreenshotTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Convert HTML or Twig files into screenshot using Chrome.
 */
#[WithBuilderConfiguration(type: 'screenshot', name: 'html')]
final class HtmlScreenshotBuilder extends AbstractBuilder implements BuilderAssetInterface
{
    use ChromiumScreenshotTrait;

    protected function executeBrowser(BrowserInterface $browser): string
    {
        $htmlContent = $this->extractHtmlContent($this->getBodyBag()->get(Part::Body->value));

        return $browser->htmlToScreenshot(
            $htmlContent,
            $this->collectScreenshotOptions(),
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
