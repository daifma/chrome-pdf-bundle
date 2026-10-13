<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\AssetPathResolverInterface;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumPdfTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\MarkdownTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;

/**
 * Convert Markdown files into PDF using Chrome.
 *
 * Wrap your markdown files into an HTML or Twig template: each {{ toHTML "file.md" }} directive of the wrapper
 * is replaced by the HTML conversion of the markdown file. In a Twig template, protect the directive with the
 * verbatim tag: {% verbatim %}{{ toHTML "file.md" }}{% endverbatim %}.
 *
 * @methodDoc files Add Markdown into a PDF.
 * Required to generate a PDF from Markdown builder. You can pass several files with that method.
 * As assets files, by default the markdown files are fetch in the assets folder of your application.
 *
 * @example files('header.md','content.md','footer.md')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'markdown')]
final class MarkdownPdfBuilder extends AbstractBuilder implements BuilderAssetInterface, AssetPathResolverInterface
{
    use ChromiumPdfTrait {
        content as private;
        contentRaw as private;
        contentFile as private;
    }
    use MarkdownTrait;

    protected function executeBrowser(BrowserInterface $browser): string
    {
        $html = $this->buildHtmlFromMarkdown($this->extractHtmlContent($this->getBodyBag()->get(Part::Body->value)));

        return $browser->htmlToPdf(
            $html,
            $this->collectPdfOptions(),
            $this->collectPageOptions(),
        );
    }
}
