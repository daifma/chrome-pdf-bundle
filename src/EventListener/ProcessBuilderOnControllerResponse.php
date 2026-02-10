<?php

namespace Daif\ChromePdfBundle\EventListener;

use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Symfony\Component\HttpKernel\Event\ViewEvent;

final class ProcessBuilderOnControllerResponse
{
    public function streamBuilder(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();

        if (!$controllerResult instanceof ChromePdfFileResult) {
            return;
        }

        $event->setResponse($controllerResult->stream());
    }
}
