<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Only PDF builders get headers and footers: Page.captureScreenshot has no equivalent.
 *
 * @package Behavior\\Content
 */
trait HeaderFooterTrait
{
    /**
     * @param array<string, mixed> $context
     */
    abstract protected function withRenderedPart(Part $part, string $template, array $context = []): static;

    abstract protected function withRawPart(Part $part, string $html): static;

    abstract protected function withFilePart(Part $part, string $path): static;

    /**
     * Twig template rendered as the header of every page.
     *
     * Chrome renders headers in an isolated context which cannot load files: chrome_pdf_asset(),
     * chrome_pdf_font_face() and chrome_pdf_font_style_tag() automatically return data: URIs in that context.
     * Use the CSS classes "date", "title", "url", "pageNumber" and "totalPages" to inject printing values.
     *
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example header('header.html.twig', ['my_var' => 'value'])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('header', children: [
        new ScalarNodeBuilder('template', required: true, restrictTo: 'string'),
        new ArrayNodeBuilder('context', normalizeKeys: false, prototype: 'variable'),
    ]))]
    public function header(string $template, array $context = []): static
    {
        return $this->withRenderedPart(Part::Header, $template, $context);
    }

    /**
     * The raw HTML string used as the header of every page.
     *
     * @example headerRaw('<html><body><h1>The header</h1></body></html>')
     */
    public function headerRaw(string $html): static
    {
        return $this->withRawPart(Part::Header, $html);
    }

    /**
     * Twig template rendered as the footer of every page (see header() for the constraints).
     *
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example footer('footer.html.twig', ['my_var' => 'value'])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('footer', children: [
        new ScalarNodeBuilder('template', required: true, restrictTo: 'string'),
        new ArrayNodeBuilder('context', normalizeKeys: false, prototype: 'variable'),
    ]))]
    public function footer(string $template, array $context = []): static
    {
        return $this->withRenderedPart(Part::Footer, $template, $context);
    }

    /**
     * The raw HTML string used as the footer of every page.
     *
     * @example footerRaw('<html><body><h6>The footer</h6></body></html>')
     */
    public function footerRaw(string $html): static
    {
        return $this->withRawPart(Part::Footer, $html);
    }

    /**
     * HTML file containing the header.
     *
     * As assets files, by default the HTML files are fetch in the assets folder of your application.
     * If your HTML files are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @throws PartRenderingException if the file could not be found
     *
     * @example headerFile('../templates/html/header.html')
     */
    public function headerFile(string $path): static
    {
        return $this->withFilePart(Part::Header, $path);
    }

    /**
     * HTML file containing the footer.
     *
     * As assets files, by default the HTML files are fetch in the assets folder of your application.
     * If your HTML files are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @throws PartRenderingException if the file could not be found
     *
     * @example footerFile('../templates/html/footer.html')
     */
    public function footerFile(string $path): static
    {
        return $this->withFilePart(Part::Footer, $path);
    }
}
