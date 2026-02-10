<?php

namespace Daif\ChromePdfBundle\Builder\Screenshot;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\ChromiumScreenshotTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\RequestContextAwareTrait;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;

/**
 * Convert a URL into screenshot using Chrome.
 */
#[WithBuilderConfiguration(type: 'screenshot', name: 'url')]
final class UrlScreenshotBuilder extends AbstractBuilder implements BuilderAssetInterface
{
    use ChromiumScreenshotTrait;
    use RequestContextAwareTrait;

    /**
     * URL of the page you want to convert into a screenshot.
     *
     * @example url('https://example.com')
     */
    public function url(string $url): self
    {
        $this->getBodyBag()->set('url', $url);

        return $this;
    }

    /**
     * Route of the page you want to convert into a screenshot.
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

        return $browser->urlToScreenshot(
            $url,
            $this->collectScreenshotOptions(),
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

    #[SubscribedService('router', nullable: true)]
    private function getUrlGenerator(): UrlGeneratorInterface
    {
        if (
            !$this->container->has('router')
            || !($urlGenerator = $this->container->get('router')) instanceof UrlGeneratorInterface
        ) {
            throw new \LogicException(\sprintf('UrlGenerator is required to use "%s" method. Try to run "composer require symfony/routing".', __METHOD__));
        }

        return $urlGenerator;
    }

    private function resolveUrl(): string
    {
        if (null !== $this->getBodyBag()->get('url')) {
            return $this->getBodyBag()->get('url');
        }

        [$route, $parameters] = $this->getBodyBag()->get('route');

        $urlGenerator = $this->getUrlGenerator();
        $context = $urlGenerator->getContext();
        $requestContext = $this->getRequestContext();

        if (null !== $requestContext) {
            $urlGenerator->setContext($requestContext);
        }

        try {
            return $urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $urlGenerator->setContext($context);
        }
    }
}
