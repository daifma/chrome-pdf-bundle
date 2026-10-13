<?php

namespace Daif\ChromePdfBundle\Messenger;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncGenerator
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param class-string<BuilderInterface> $builderClass
     * @param array<string, mixed>           $bodyBag
     *
     * @return string the correlation id sent to the events webhook
     */
    public function dispatch(string $builderClass, array $bodyBag, string|null $fileName, string $disposition): string
    {
        $correlationId = bin2hex(random_bytes(16));

        $this->bus->dispatch(new GenerateDocumentMessage($builderClass, $bodyBag, $fileName, $disposition, $correlationId));

        return $correlationId;
    }
}
