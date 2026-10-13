<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\TwigAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Builder\ValueObject\FilePart;
use Daif\ChromePdfBundle\Builder\ValueObject\RenderedPart;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\Twig\ChromePdfExtension;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

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
    public function content(string $template, array $context = []): static
    {
        return $this->withRenderedPart(Part::Body, $template, $context);
    }

    /**
     * The raw HTML string to convert.
     *
     * Warning: assets (css, images, etc...) referenced in the HTML are not discovered automatically,
     * declare them with assets()/addAsset() and reference them by their basename.
     *
     * @example contentRaw('<html><body><h2>The content</h2></body></html>')
     */
    public function contentRaw(string $html): static
    {
        return $this->withRawPart(Part::Body, $html);
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
    public function contentFile(string $path): static
    {
        return $this->withFilePart(Part::Body, $path);
    }

    /**
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     */
    protected function withRenderedPart(Part $part, string $template, array $context = []): static
    {
        $twig = $this->getTwig();
        $runtime = $twig->hasExtension(ChromePdfExtension::class) ? $twig->getRuntime(ChromePdfRuntime::class) : null;
        $previousBuilder = $runtime?->getBuilder();
        $previousPart = $runtime?->getPart();

        $runtime?->setBuilder($this instanceof BuilderAssetInterface ? $this : null, $part);
        try {
            $renderedPart = new RenderedPart($part, $twig->render($template, array_merge($context, ['_builder' => $this])));
        } catch (\Throwable $t) {
            throw new PartRenderingException(\sprintf('Could not render template "%s" into PDF part "%s". %s', $template, $part->value, $t->getMessage()), previous: $t);
        } finally {
            $runtime?->setBuilder($previousBuilder, $previousPart);
        }

        $this->getBodyBag()->set($part->value, $renderedPart);

        return $this;
    }

    protected function withRawPart(Part $part, string $html): static
    {
        $this->getBodyBag()->set($part->value, new RenderedPart($part, $html));

        return $this;
    }

    /**
     * @throws PartRenderingException if the file could not be found
     */
    protected function withFilePart(Part $part, string $path): static
    {
        try {
            $resolvedPath = $this->getAssetBaseDirFormatter()->resolve($path);
        } catch (FileNotFoundException $e) {
            throw new PartRenderingException(\sprintf('Could not render file into PDF part "%s". %s', $part->value, $e->getMessage()), previous: $e);
        }

        if (!is_file($resolvedPath)) {
            throw new PartRenderingException(\sprintf('Could not render file into PDF part "%s". File located at "%s" is not found.', $part->value, $resolvedPath));
        }

        $this->getBodyBag()->set($part->value, new FilePart($part, $resolvedPath));

        return $this;
    }
}
