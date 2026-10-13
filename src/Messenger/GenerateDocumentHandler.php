<?php

namespace Daif\ChromePdfBundle\Messenger;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\Exception\DownloadFromException;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\RuntimeException;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates the document of a {@see GenerateDocumentMessage} and sends it to the webhook URL.
 * On failure, a JSON body {"status": int, "message": string} is sent to the error URL instead.
 */
final class GenerateDocumentHandler
{
    public function __construct(
        private readonly ContainerInterface $builders,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface|null $logger = null,
        private readonly DownloadGuard|null $urlGuard = null,
    ) {
    }

    public function __invoke(GenerateDocumentMessage $message): void
    {
        $bag = $message->bodyBag;

        $successUrl = self::stringOrNull($bag['webhookUrl'] ?? null) ?? throw new MissingRequiredFieldException('A webhook URL is required to generate a document asynchronously.');
        $successMethod = self::stringOrNull($bag['webhookMethod'] ?? null) ?? 'POST';
        $errorUrl = self::stringOrNull($bag['webhookErrorUrl'] ?? null) ?? $successUrl;
        $errorMethod = self::stringOrNull($bag['webhookErrorMethod'] ?? null) ?? 'POST';
        $eventsUrl = self::stringOrNull($bag['webhookEventsUrl'] ?? null);
        $extraHeaders = \is_array($bag['webhookExtraHttpHeaders'] ?? null) ? $bag['webhookExtraHttpHeaders'] : [];

        try {
            $result = $this->createBuilder($message)->generate();
            $content = $result->processor(new InMemoryProcessor())->process();
        } catch (\Throwable $e) {
            $status = $e instanceof MissingRequiredFieldException || $e instanceof InvalidBuilderConfiguration ? 400 : 500;
            $this->logger?->error('Asynchronous generation of {builder} failed: {message}', ['builder' => $message->builderClass, 'message' => $e->getMessage(), 'exception' => $e]);
            // engine and browser errors carry local paths and tool output: they stay in the logs
            $publicMessage = 400 === $status ? $e->getMessage() : \sprintf('The document could not be generated, see the server logs (correlation id "%s").', $message->correlationId);

            $this->send($errorMethod, $errorUrl, [
                'headers' => ['Content-Type' => 'application/json'] + $extraHeaders,
                'json' => ['status' => $status, 'message' => $publicMessage],
            ]);
            $this->sendEvent($eventsUrl, 'webhook.error', $message, $extraHeaders, ['error' => ['status' => $status, 'message' => $publicMessage]]);

            return;
        }

        $fileName = $result->getFileName() ?? 'document.'.self::extension($result->getContentType());
        $this->send($successMethod, $successUrl, [
            'headers' => [
                'Content-Type' => $result->getContentType(),
                'Content-Disposition' => HeaderUtils::makeDisposition($message->disposition, $fileName, self::asciiFallback($fileName)),
            ] + $extraHeaders,
            'body' => $content,
        ]);
        $this->sendEvent($eventsUrl, 'webhook.success', $message, $extraHeaders, ['fileName' => $fileName, 'contentType' => $result->getContentType(), 'contentLength' => $result->getContentLength()]);
    }

    private function createBuilder(GenerateDocumentMessage $message): AbstractBuilder
    {
        if (!$this->builders->has($message->builderClass)) {
            throw new LogicException(\sprintf('The builder "%s" is not registered as a service tagged "daif_chrome_pdf.builder".', $message->builderClass));
        }

        $builder = $this->builders->get($message->builderClass);
        if ($builder instanceof TraceableBuilder) {
            $builder = $builder->getInner();
        }

        if (!$builder instanceof AbstractBuilder) {
            throw new LogicException(\sprintf('The builder "%s" must extend "%s" to be generated asynchronously.', $message->builderClass, AbstractBuilder::class));
        }

        return $builder->restoreState($message->bodyBag, $message->fileName, $message->disposition);
    }

    /**
     * @param array<string, mixed> $extraHeaders
     * @param array<string, mixed> $payload
     */
    private function sendEvent(string|null $url, string $type, GenerateDocumentMessage $message, array $extraHeaders, array $payload): void
    {
        if (null === $url) {
            return;
        }

        try {
            $this->send('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'] + $extraHeaders,
                'json' => [
                    'type' => $type,
                    'correlationId' => $message->correlationId,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
                ] + $payload,
            ]);
        } catch (RuntimeException|UnrecoverableMessageHandlingException $e) {
            // The document was already delivered, a failing events endpoint must not trigger a retry.
            $this->logger?->warning('Webhook events call failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws RuntimeException when the webhook cannot be reached or answers with an error status (Messenger may then retry)
     */
    private function send(string $method, string $url, array $options): void
    {
        try {
            $this->urlGuard?->assertAllowed($url);
        } catch (DownloadFromException $e) {
            // a blocked URL will stay blocked: no retry
            throw new UnrecoverableMessageHandlingException(\sprintf('The webhook URL "%s" is not allowed by daif_chrome_pdf.allow_list / deny_list.', $url), previous: $e);
        }

        try {
            $statusCode = $this->httpClient->request($method, $url, $options + ['max_redirects' => 0])->getStatusCode();
        } catch (\Throwable $e) {
            throw new RuntimeException(\sprintf('The webhook call to "%s" failed: %s', $url, $e->getMessage()), previous: $e);
        }

        if ($statusCode >= 300) {
            throw new RuntimeException(\sprintf('The webhook call to "%s" returned the HTTP status %d.', $url, $statusCode));
        }
    }

    private static function stringOrNull(mixed $value): string|null
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private static function extension(string $contentType): string
    {
        return match ($contentType) {
            'application/zip' => 'zip',
            'image/png' => 'png',
            'image/jpeg' => 'jpeg',
            'image/webp' => 'webp',
            default => 'pdf',
        };
    }

    private static function asciiFallback(string $fileName): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', $fileName);

        return null === $fallback || '' === $fallback ? 'document' : $fallback;
    }
}
