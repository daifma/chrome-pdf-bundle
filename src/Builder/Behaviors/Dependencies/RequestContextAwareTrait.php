<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Dependencies;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;

trait RequestContextAwareTrait
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService('.daif_chrome_pdf.request_context', nullable: true, attributes: new Autowire(service: '.daif_chrome_pdf.request_context'))]
    protected function getRequestContext(): RequestContext|null
    {
        if (!$this->container->has('.daif_chrome_pdf.request_context')) {
            return null;
        }

        return $this->container->get('.daif_chrome_pdf.request_context');
    }
}
