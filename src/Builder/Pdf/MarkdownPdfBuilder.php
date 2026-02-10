<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumPdfTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use League\CommonMark\CommonMarkConverter;

/**
 * Convert Markdown files into PDF using Chrome.
 *
 * Wrap your markdown file into an HTML or Twig template. The markdown content
 * will be converted to HTML and injected into the template before rendering.
 *
 * @methodDoc files Add Markdown into a PDF.
 * Required to generate a PDF from Markdown builder. You can pass several files with that method.
 * As assets files, by default the markdown files are fetch in the assets folder of your application.
 *
 * @example files('header.md','content.md','footer.md')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'markdown')]
final class MarkdownPdfBuilder extends AbstractBuilder implements BuilderAssetInterface
{
    use ChromiumPdfTrait {
        content as private;
        contentFile as private;
    }
    use FilesTrait;

    private const AVAILABLE_EXTENSIONS = [
        'md',
    ];

    /**
     * The template that wraps the markdown content.
     *
     * The markdown files will be converted to HTML and made available in the template.
     *
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example wrapper('wrapper.html.twig', ['my_var' => 'value'])
     */
    public function wrapper(string $template, array $context = []): self
    {
        return $this->content($template, $context);
    }

    /**
     * The HTML file that wraps the markdown content.
     *
     * As assets files, by default the markdown files are fetch in the assets folder of your application.
     *
     * @example wrapperFile('../templates/wrapper.html')
     */
    public function wrapperFile(string $path): self
    {
        return $this->contentFile($path);
    }

    protected function getAllowedFilesExtensions(): array
    {
        return self::AVAILABLE_EXTENSIONS;
    }

    protected function executeBrowser(BrowserInterface $browser): string
    {
        $htmlContent = $this->buildHtmlFromMarkdown();

        return $browser->htmlToPdf(
            $htmlContent,
            $this->collectPdfOptions(),
            $this->collectPageOptions(),
        );
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get(Part::Body->value) === null) {
            throw new MissingRequiredFieldException('HTML template is required');
        }

        if ($this->getBodyBag()->get('files') === null) {
            throw new MissingRequiredFieldException('At least one markdown file is required.');
        }
    }

    /**
     * Build final HTML by converting markdown files to HTML and injecting
     * them into the wrapper template.
     */
    private function buildHtmlFromMarkdown(): string
    {
        $wrapperHtml = $this->extractHtmlContent($this->getBodyBag()->get(Part::Body->value));

        $converter = new CommonMarkConverter();

        /** @var array<string, \SplFileInfo> $files */
        $files = $this->getBodyBag()->get('files') ?? [];

        foreach ($files as $path => $fileInfo) {
            $markdownContent = file_get_contents($fileInfo->getPathname());
            if (false === $markdownContent) {
                throw new PartRenderingException(\sprintf('Could not read markdown file "%s".', $fileInfo->getPathname()));
            }

            $htmlContent = $converter->convert($markdownContent)->getContent();

            // Replace {{ toHTML "filename.md" }} directives in the wrapper
            $basename = basename($path);
            $wrapperHtml = str_replace(
                ['{{ toHTML "'.$basename.'" }}', '{{ toHTML \''.$basename.'\' }}'],
                $htmlContent,
                $wrapperHtml,
            );
        }

        return $wrapperHtml;
    }
}
