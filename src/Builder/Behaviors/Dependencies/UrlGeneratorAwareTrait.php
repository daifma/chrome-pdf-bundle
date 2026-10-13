<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Dependencies;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;

trait UrlGeneratorAwareTrait
{
    use RequestContextAwareTrait;
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService('router', nullable: true)]
    protected function getUrlGenerator(): UrlGeneratorInterface
    {
        if (
            !$this->container->has('router')
            || !($urlGenerator = $this->container->get('router')) instanceof UrlGeneratorInterface
        ) {
            throw new \LogicException(\sprintf('UrlGenerator is required to use "%s" method. Try to run "composer require symfony/routing".', __METHOD__));
        }

        return $urlGenerator;
    }

    /**
     * Generates an absolute URL, using request_context.base_uri when configured.
     *
     * @param array<mixed> $parameters
     */
    protected function generateAbsoluteUrl(string $route, array $parameters = []): string
    {
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
