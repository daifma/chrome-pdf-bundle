<?php

namespace Daif\ChromePdfBundle\Tests\Functional\Async;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * MockHttpClient response factory recording the webhook calls.
 */
final class WebhookRecorder
{
    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, body: string}>
     */
    public array $requests = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $headers = [];
        foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $header) {
            if (\is_string($header) && str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        $body = $options['body'] ?? '';
        if ($body instanceof \Closure) {
            $content = '';
            while ('' !== $chunk = $body(16372)) {
                $content .= \is_string($chunk) ? $chunk : '';
            }
            $body = $content;
        }

        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => \is_string($body) ? $body : '',
        ];

        return new MockResponse('', ['http_code' => 204]);
    }
}
