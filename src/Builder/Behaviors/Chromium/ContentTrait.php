<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\TwigAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\ValueObject\RenderedPart;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;

/**
 * @package Behavior\\Content
 */
trait ContentTrait
{
    use AssetBaseDirFormatterAwareTrait;
    use TwigAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example content('content.html.twig', ['my_var' => 'value'])
     */
    public function content(string $template, array $context = []): self
    {
        return $this->withRenderedPart(Part::Body, $template, $context);
    }

    /**
     * The HTML file to convert into PDF.
     *
     * As assets files, by default the HTML files are fetch in the assets folder of your application.
     * If your HTML files are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example contentFile('../public/content.html')
     */
    public function contentFile(string $path): self
    {
        return $this->withFilePart(Part::Body, $path);
    }

    /**
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
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example footer('header.html.twig', ['my_var' => 'value'])
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
     * HTML file containing the header.
     *
     * As assets files, by default the HTML files are fetch in the assets folder of your application.
     * If your HTML files are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @throws PartRenderingException if the template could not be rendered
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
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example footerFile('../templates/html/footer.html')
     */
    public function footerFile(string $path): static
    {
        return $this->withFilePart(Part::Footer, $path);
    }

    /**
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     */
    protected function withRenderedPart(Part $part, string $template, array $context = []): static
    {
        $this->getTwig()->getRuntime(ChromePdfRuntime::class)->setBuilder($this);
        try {
            $renderedPart = new RenderedPart($part, $this->getTwig()->render($template, array_merge($context, ['_builder' => $this])));
        } catch (\Throwable $t) {
            throw new PartRenderingException(\sprintf('Could not render template "%s" into PDF part "%s". %s', $template, $part->value, $t->getMessage()), previous: $t);
        } finally {
            $this->getTwig()->getRuntime(ChromePdfRuntime::class)->setBuilder(null);
        }

        $this->getBodyBag()->set($part->value, $renderedPart);

        return $this;
    }

    /**
     * @throws PartRenderingException if the template could not be rendered
     */
    protected function withFilePart(Part $part, string $path): static
    {
        $resolvedPath = $this->getAssetBaseDirFormatter()->resolve($path);
        if (!file_exists($resolvedPath)) {
            throw new PartRenderingException(\sprintf('Could not render file into PDF part "%s". File located at "%s" is not found.', $part->value, $resolvedPath));
        }

        $this->getBodyBag()->set($part->value, new \SplFileInfo($resolvedPath));

        return $this;
    }
}
