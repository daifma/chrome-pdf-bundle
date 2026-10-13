<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\AssetPathResolverInterface;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumPdfTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\UrlGeneratorAwareTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;

/**
 * Convert a URL into PDF using Chrome.
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'url')]
final class UrlPdfBuilder extends AbstractBuilder implements BuilderAssetInterface, AssetPathResolverInterface
{
    use ChromiumPdfTrait;
    use UrlGeneratorAwareTrait;

    /**
     * URL of the page you want to convert into PDF.
     *
     * @example url('https://example.com')
     */
    public function url(string $url): self
    {
        $this->getBodyBag()->set('url', $url);

        return $this;
    }

    /**
     * Route of the page you want to convert into PDF.
     *
     * You must provide a URL accessible by Chrome with a public Host.
     * Or configure request_context.base_uri in daif_chrome_pdf.yaml
     *
     * @param string       $name       #Route
     * @param array<mixed> $parameters
     *
     * @example route('home', ['my_var' => 'value'])
     */
    public function route(string $name, array $parameters = []): self
    {
        $this->getBodyBag()->set('route', [$name, $parameters]);

        return $this;
    }

    protected function executeBrowser(BrowserInterface $browser): string
    {
        $url = $this->resolveUrl();

        return $browser->urlToPdf(
            $url,
            $this->collectPdfOptions(),
            $this->collectPageOptions(),
        );
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get('url') === null && $this->getBodyBag()->get('route') === null) {
            throw new MissingRequiredFieldException('"url" (or "route") is required');
        }

        if ($this->getBodyBag()->get('url') !== null && $this->getBodyBag()->get('route') !== null) {
            throw new MissingRequiredFieldException('Provide only one of ["route", "url"] parameter. Not both.');
        }
    }

    private function resolveUrl(): string
    {
        if (null !== $this->getBodyBag()->get('url')) {
            return $this->getBodyBag()->get('url');
        }

        [$route, $parameters] = $this->getBodyBag()->get('route');

        return $this->generateAbsoluteUrl($route, $parameters);
    }
}
