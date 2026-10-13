<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Dependencies;

use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistryInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;

trait WebhookConfigurationRegistryAwareTrait
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService('webhook_configuration_registry', nullable: true)]
    protected function getWebhookConfigurationRegistry(): WebhookConfigurationRegistryInterface
    {
        if (
            !$this->container->has('webhook_configuration_registry')
            || !($registry = $this->container->get('webhook_configuration_registry')) instanceof WebhookConfigurationRegistryInterface
        ) {
            throw new LogicException('The webhook configuration registry is missing: webhookConfiguration() requires the DaifChromePdfBundle "webhook" configuration.');
        }

        return $registry;
    }
}
