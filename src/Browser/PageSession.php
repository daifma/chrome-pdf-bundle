<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use Psr\Log\LoggerInterface;

/**
 * Drives one Chrome tab through raw CDP for a single conversion: request filtering,
 * page setup, navigation, waits, "fail on" checks, printing and screenshots.
 *
 * Request filtering uses the Fetch domain: every request (documents, iframes, images, CSS,
 * fonts, XHR/fetch, including file:// URLs) is paused and either continued or failed with
 * "AccessDenied" according to the RequestFilter. Out-of-process iframes and workers are
 * auto-attached (Target.setAutoAttach, paused until the filter is installed) so their
 * requests go through the same filter. It has been verified with Chromium 155 that
 * <iframe src="file:///etc/passwd">, <img src="file:///..."> and fetch("file:///...")
 * are intercepted and blocked this way.
 *
 * Every wait is bounded by the configured timeout (in milliseconds).
 *
 * @internal
 */
final class PageSession
{
    private const POLL_INTERVAL_MS = 100;
    private const IO_READ_SIZE = 1024 * 1024;
    private const SINGLE_PAGE_ATTEMPTS = 3;

    private readonly Session $session;
    private readonly Connection $connection;
    private readonly string $mainFrameId;

    private string|null $loaderId = null;

    /** @var array<string, array<string, true>> loaderId => lifecycle event names */
    private array $lifecycle = [];

    /** @var array<string, string> requestId => URL */
    private array $requestUrls = [];

    /** @var array<string, array{url: string, status: int, type: string}> requestId => response */
    private array $responses = [];

    /** @var array<string, array{url: string, error: string, canceled: bool, type: string}> requestId => failure */
    private array $loadingFailures = [];

    /** @var array<string, string> URL => block reason */
    private array $blocked = [];

    /** @var list<string> */
    private array $consoleExceptions = [];

    /** @var array<string, Session> sessionId => child session (OOPIF, workers) */
    private array $childSessions = [];

    /** @var list<int> ids of fire-and-forget messages whose responses must be discarded */
    private array $pendingMessageIds = [];

    private string|null $crashed = null;

    private string|null $blockedNavigation = null;

    private bool $closed = false;

    public function __construct(
        private readonly Page $page,
        private readonly RequestFilter $filter,
        private readonly int $timeout,
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->session = $page->getSession();
        $this->connection = $this->session->getConnection();
        $this->mainFrameId = $page->getFrameManager()->getMainFrame()->getFrameId();

        $this->attachListeners($this->session, true);
        // synchronous on purpose: a failure here must abort the conversion, not disable the filtering
        $this->call('Fetch.enable', ['patterns' => [['urlPattern' => '*', 'requestStage' => 'Request']]]);
        $this->call('Target.setAutoAttach', ['autoAttach' => true, 'waitForDebuggerOnStart' => true, 'flatten' => true]);
    }

    /**
     * @param array<string, mixed>                                          $pageOptions
     * @param array{width: int, height: int, deviceScaleFactor: float}|null $viewport
     */
    public function open(string $url, array $pageOptions, array|null $viewport = null): void
    {
        $this->setup($pageOptions, $viewport);
        $this->navigate($url, (string) ($pageOptions['waitEvent'] ?? 'load'));
        $this->applyWaits($pageOptions);
        $this->checkFailures($pageOptions);
    }

    /**
     * @param array<string, mixed> $pdfOptions
     * @param array<string, mixed> $pageOptions
     */
    public function printToPdf(array $pdfOptions, array $pageOptions = []): string
    {
        $params = PrintOptions::toCdp($pdfOptions);

        if (empty($pdfOptions['singlePage'])) {
            return $this->doPrint($params);
        }

        $params['paperHeight'] = PrintOptions::singlePageHeight($params, $this->measureDocumentHeight($params, $pageOptions));
        $pdf = '';
        for ($attempt = 1; $attempt <= self::SINGLE_PAGE_ATTEMPTS; ++$attempt) {
            $pdf = $this->doPrint($params);
            if (PrintOptions::countPdfPages($pdf) <= 1) {
                break;
            }
            // layout differs slightly between screen measurement and print: grow and retry
            $params['paperHeight'] *= 1.05;
        }

        return $pdf;
    }

    /**
     * @param array<string, mixed> $screenshotOptions
     */
    public function captureScreenshot(array $screenshotOptions): string
    {
        $format = strtolower((string) ($screenshotOptions['format'] ?? 'png'));
        if (!\in_array($format, ['png', 'jpeg', 'webp'], true)) {
            throw new BrowserException(\sprintf('Unsupported screenshot format "%s" (expected png, jpeg or webp).', $format));
        }

        $params = ['format' => $format, 'fromSurface' => true];
        if ('png' !== $format && isset($screenshotOptions['quality'])) {
            $params['quality'] = max(0, min(100, (int) $screenshotOptions['quality']));
        }
        if (!empty($screenshotOptions['optimizeForSpeed'])) {
            $params['optimizeForSpeed'] = true;
        }

        if (empty($screenshotOptions['clip'])) {
            // full page: viewport width, whole document height
            $metrics = $this->call('Page.getLayoutMetrics');
            $contentHeight = (float) ($metrics['cssContentSize']['height'] ?? $metrics['contentSize']['height'] ?? 0);
            $viewportHeight = (int) ($screenshotOptions['height'] ?? 600);
            $params['captureBeyondViewport'] = true;
            $params['clip'] = [
                'x' => 0,
                'y' => 0,
                'width' => (int) ($screenshotOptions['width'] ?? 800),
                'height' => max($viewportHeight, (int) ceil($contentHeight)),
                'scale' => 1,
            ];
        }

        $result = $this->call('Page.captureScreenshot', $params);
        $data = base64_decode((string) ($result['data'] ?? ''), true);
        if (false === $data || '' === $data) {
            throw new BrowserException('Chrome returned an empty screenshot.');
        }

        return $data;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        foreach ($this->childSessions as $child) {
            try {
                $child->destroy();
            } catch (\Throwable) {
                // already destroyed
            }
        }
        $this->childSessions = [];

        try {
            if ($this->connection->isConnected()) {
                $this->page->close();
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('Unable to close the Chrome tab: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }

        $this->discardPendingResponses();
    }

    /**
     * @param array<string, mixed>                                          $pageOptions
     * @param array{width: int, height: int, deviceScaleFactor: float}|null $viewport
     */
    private function setup(array $pageOptions, array|null $viewport): void
    {
        if (null !== $viewport) {
            $this->call('Emulation.setDeviceMetricsOverride', [
                'width' => max(1, $viewport['width']),
                'height' => max(1, $viewport['height']),
                'deviceScaleFactor' => $viewport['deviceScaleFactor'] > 0 ? $viewport['deviceScaleFactor'] : 1.0,
                'mobile' => false,
            ]);
        }

        if (isset($pageOptions['userAgent']) && '' !== $pageOptions['userAgent']) {
            $this->call('Network.setUserAgentOverride', ['userAgent' => (string) $pageOptions['userAgent']]);
        }

        if (!empty($pageOptions['extraHttpHeaders']) && \is_array($pageOptions['extraHttpHeaders'])) {
            $headers = [];
            foreach ($pageOptions['extraHttpHeaders'] as $name => $value) {
                $headers[(string) $name] = (string) $value;
            }
            $this->call('Network.setExtraHTTPHeaders', ['headers' => $headers]);
        }

        if (!empty($pageOptions['cookies']) && \is_array($pageOptions['cookies'])) {
            $this->call('Network.setCookies', ['cookies' => self::buildCookies($pageOptions['cookies'])]);
        }

        $this->applyMediaEmulation($pageOptions);

        if (!empty($pageOptions['omitBackground'])) {
            $this->call('Emulation.setDefaultBackgroundColorOverride', ['color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]]);
        }
    }

    /**
     * @param array<string, mixed> $pageOptions
     */
    private function applyMediaEmulation(array $pageOptions, string|null $forceMedia = null): void
    {
        $media = $forceMedia ?? (isset($pageOptions['emulatedMediaType']) ? (string) $pageOptions['emulatedMediaType'] : '');
        $features = [];
        foreach ((array) ($pageOptions['emulatedMediaFeatures'] ?? []) as $feature) {
            if (!\is_array($feature) || !isset($feature['name'], $feature['value'])) {
                throw new BrowserException('Each emulated media feature must be an array with "name" and "value" keys.');
            }
            $features[] = ['name' => (string) $feature['name'], 'value' => (string) $feature['value']];
        }

        if ('' === $media && [] === $features && null === $forceMedia) {
            return;
        }

        $params = ['media' => $media];
        if ([] !== $features) {
            $params['features'] = $features;
        }
        $this->call('Emulation.setEmulatedMedia', $params);
    }

    /**
     * @param array<mixed> $cookies
     *
     * @return list<array<string, mixed>>
     */
    private static function buildCookies(array $cookies): array
    {
        $result = [];
        foreach ($cookies as $cookie) {
            if (!\is_array($cookie) || !isset($cookie['name'], $cookie['value'], $cookie['domain'])) {
                throw new BrowserException('Each cookie must be an array with at least "name", "value" and "domain" keys.');
            }

            $cdpCookie = [
                'name' => (string) $cookie['name'],
                'value' => (string) $cookie['value'],
                'domain' => (string) $cookie['domain'],
                'path' => (string) ($cookie['path'] ?? '/'),
            ];
            if (isset($cookie['secure'])) {
                $cdpCookie['secure'] = (bool) $cookie['secure'];
            }
            if (isset($cookie['httpOnly'])) {
                $cdpCookie['httpOnly'] = (bool) $cookie['httpOnly'];
            }
            if (isset($cookie['sameSite']) && '' !== $cookie['sameSite']) {
                $sameSite = ucfirst(strtolower((string) $cookie['sameSite']));
                if (!\in_array($sameSite, ['Strict', 'Lax', 'None'], true)) {
                    throw new BrowserException(\sprintf('Invalid cookie sameSite value "%s".', $cookie['sameSite']));
                }
                $cdpCookie['sameSite'] = $sameSite;
            }
            $result[] = $cdpCookie;
        }

        return $result;
    }

    private function navigate(string $url, string $waitEvent): void
    {
        $lifecycleEvent = match ($waitEvent) {
            'load' => 'load',
            'networkAlmostIdle' => 'networkAlmostIdle',
            'networkIdle' => 'networkIdle',
            default => throw new BrowserException(\sprintf('Invalid waitEvent "%s" (expected load, networkAlmostIdle or networkIdle).', $waitEvent)),
        };

        $result = $this->call('Page.navigate', ['url' => $url, 'frameId' => $this->mainFrameId]);

        if (!empty($result['errorText'])) {
            $reason = isset($this->blocked[$url]) ? ' (blocked: '.$this->blocked[$url].')' : '';
            throw new BrowserException(\sprintf('Navigation to "%s" failed: %s%s.', self::shortUrl($url), $result['errorText'], $reason));
        }

        $loaderId = $result['loaderId'] ?? null;
        if (!\is_string($loaderId) || '' === $loaderId) {
            throw new BrowserException(\sprintf('Navigation to "%s" did not start a new document.', self::shortUrl($url)));
        }
        $this->loaderId = $loaderId;

        $this->pump(
            fn (): bool => isset($this->lifecycle[(string) $this->loaderId][$lifecycleEvent]),
            $this->timeout,
            \sprintf('waiting for the "%s" event of "%s"', $waitEvent, self::shortUrl($url)),
        );
    }

    /**
     * @param array<string, mixed> $pageOptions
     */
    private function applyWaits(array $pageOptions): void
    {
        if (isset($pageOptions['waitDelay']) && '' !== $pageOptions['waitDelay']) {
            $delay = (string) $pageOptions['waitDelay'];
            if (1 !== preg_match('/^(\d+)(ms|s)$/', $delay, $matches)) {
                throw new BrowserException(\sprintf('Invalid waitDelay "%s" (expected e.g. "500ms" or "2s").', $delay));
            }
            $this->sleep((int) $matches[1] * ('s' === $matches[2] ? 1000 : 1));
        }

        if (isset($pageOptions['waitForExpression']) && '' !== $pageOptions['waitForExpression']) {
            $expression = (string) $pageOptions['waitForExpression'];
            $this->waitUntilTruthy($expression, \sprintf('waiting for the expression "%s" to be truthy', $expression));
        }

        if (isset($pageOptions['waitForSelector']) && '' !== $pageOptions['waitForSelector']) {
            $selector = (string) $pageOptions['waitForSelector'];
            $expression = \sprintf('document.querySelector(%s) !== null', json_encode($selector, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
            $this->waitUntilTruthy($expression, \sprintf('waiting for the selector "%s"', $selector));
        }
    }

    private function waitUntilTruthy(string $expression, string $what): void
    {
        $deadline = self::now() + $this->timeout;
        $lastError = null;

        while (true) {
            $remaining = (int) ceil($deadline - self::now());
            if ($remaining <= 0) {
                break;
            }

            try {
                $result = $this->call('Runtime.evaluate', [
                    'expression' => $expression,
                    'objectGroup' => 'chrome-pdf-wait',
                    'returnByValue' => false,
                    'awaitPromise' => true,
                ], $remaining);
                $this->send($this->session, 'Runtime.releaseObjectGroup', ['objectGroup' => 'chrome-pdf-wait']);

                if (isset($result['exceptionDetails'])) {
                    $lastError = self::describeException($result['exceptionDetails']);
                } elseif (self::isTruthy($result['result'] ?? [])) {
                    return;
                }
            } catch (BrowserException $e) {
                if (null !== $this->crashed || null !== $this->blockedNavigation || !$this->connection->isConnected()) {
                    throw $e;
                }
                // e.g. execution context destroyed by a navigation: keep polling
                $lastError = $e->getMessage();
            }

            $this->sleep((int) min(self::POLL_INTERVAL_MS, max(0, $deadline - self::now())));
        }

        throw new BrowserException(\sprintf('Timed out after %d ms %s.%s', $this->timeout, $what, null !== $lastError ? ' Last error: '.$lastError : ''));
    }

    /**
     * @param array<string, mixed> $remoteObject Runtime.RemoteObject
     */
    private static function isTruthy(array $remoteObject): bool
    {
        $type = $remoteObject['type'] ?? 'undefined';
        $value = $remoteObject['value'] ?? null;
        $unserializable = $remoteObject['unserializableValue'] ?? null;

        return match ($type) {
            'undefined' => false,
            'boolean' => true === $value,
            'number' => null !== $unserializable ? !\in_array($unserializable, ['NaN', '-0'], true) : 0 != $value,
            'string' => '' !== $value,
            'bigint' => '0n' !== $unserializable,
            'object' => 'null' !== ($remoteObject['subtype'] ?? null),
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $pageOptions
     */
    private function checkFailures(array $pageOptions): void
    {
        $mainResponse = null !== $this->loaderId ? ($this->responses[$this->loaderId] ?? null) : null;

        $codes = self::intList($pageOptions['failOnHttpStatusCodes'] ?? []);
        if ([] !== $codes && null !== $mainResponse && HttpStatusMatcher::matches($mainResponse['status'], $codes)) {
            throw new BrowserException(\sprintf('Invalid HTTP status code %d for the main page "%s".', $mainResponse['status'], self::shortUrl($mainResponse['url'])));
        }

        $codes = self::intList($pageOptions['failOnResourceHttpStatusCodes'] ?? []);
        if ([] !== $codes) {
            $ignoredDomains = array_values(array_map('strval', (array) ($pageOptions['ignoreResourceHttpStatusDomains'] ?? [])));
            $offending = [];
            foreach ($this->responses as $requestId => $response) {
                if ($requestId === $this->loaderId || !HttpStatusMatcher::matches($response['status'], $codes)) {
                    continue;
                }
                $host = (string) parse_url($response['url'], \PHP_URL_HOST);
                if ([] !== $ignoredDomains && HttpStatusMatcher::hostMatchesDomains($host, $ignoredDomains)) {
                    continue;
                }
                $offending[] = \sprintf('%d %s', $response['status'], self::shortUrl($response['url']));
            }
            if ([] !== $offending) {
                throw new BrowserException(\sprintf('Invalid HTTP status code for %d resource(s): %s.', \count($offending), implode(', ', array_unique($offending))));
            }
        }

        if (!empty($pageOptions['failOnResourceLoadingFailed'])) {
            $offending = [];
            foreach ($this->loadingFailures as $requestId => $failure) {
                if ($requestId === $this->loaderId || $failure['canceled']) {
                    continue;
                }
                $reason = isset($this->blocked[$failure['url']]) ? ' (blocked: '.$this->blocked[$failure['url']].')' : '';
                $offending[] = \sprintf('%s: %s%s', self::shortUrl($failure['url']), $failure['error'], $reason);
            }
            if ([] !== $offending) {
                throw new BrowserException(\sprintf('Failed to load %d resource(s): %s.', \count($offending), implode(', ', array_unique($offending))));
            }
        }

        if (!empty($pageOptions['failOnConsoleExceptions']) && [] !== $this->consoleExceptions) {
            throw new BrowserException(\sprintf('JavaScript exception(s) in the page: %s', implode(' | ', array_unique($this->consoleExceptions))));
        }
    }

    /**
     * Lays the document out at the printable width with print media and returns its height in CSS px.
     *
     * @param array<string, mixed> $params      Page.printToPDF parameters
     * @param array<string, mixed> $pageOptions
     */
    private function measureDocumentHeight(array $params, array $pageOptions): float
    {
        $this->call('Emulation.setDeviceMetricsOverride', [
            'width' => (int) round(PrintOptions::printableWidthPx($params)),
            'height' => 1,
            'deviceScaleFactor' => 1,
            'mobile' => false,
        ]);
        $isScreen = 'screen' === ($pageOptions['emulatedMediaType'] ?? null);
        if (!$isScreen) {
            $this->applyMediaEmulation($pageOptions, 'print');
        }

        try {
            $result = $this->call('Runtime.evaluate', [
                'expression' => 'Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0, Math.ceil(document.documentElement.getBoundingClientRect().height))',
                'returnByValue' => true,
            ]);
        } finally {
            $this->call('Emulation.clearDeviceMetricsOverride');
            if (!$isScreen) {
                $this->applyMediaEmulation($pageOptions, (string) ($pageOptions['emulatedMediaType'] ?? ''));
            }
        }

        $height = $result['result']['value'] ?? null;
        if (!is_numeric($height) || $height <= 0) {
            throw new BrowserException('Unable to measure the document height for singlePage.');
        }

        return (float) $height;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function doPrint(array $params): string
    {
        $params['transferMode'] = 'ReturnAsStream';
        $result = $this->call('Page.printToPDF', $params);

        if (isset($result['stream']) && \is_string($result['stream'])) {
            return $this->readStream($result['stream']);
        }

        $data = base64_decode((string) ($result['data'] ?? ''), true);
        if (false === $data || '' === $data) {
            throw new BrowserException('Chrome returned an empty PDF.');
        }

        return $data;
    }

    private function readStream(string $handle): string
    {
        $pdf = '';
        try {
            while (true) {
                $chunk = $this->call('IO.read', ['handle' => $handle, 'size' => self::IO_READ_SIZE]);
                $data = (string) ($chunk['data'] ?? '');
                if (!empty($chunk['base64Encoded'])) {
                    $decoded = base64_decode($data, true);
                    if (false === $decoded) {
                        throw new BrowserException('Chrome returned an invalid PDF stream chunk.');
                    }
                    $data = $decoded;
                }
                $pdf .= $data;
                if (!empty($chunk['eof'])) {
                    break;
                }
            }
        } finally {
            $this->send($this->session, 'IO.close', ['handle' => $handle]);
        }

        if ('' === $pdf) {
            throw new BrowserException('Chrome returned an empty PDF.');
        }

        return $pdf;
    }

    private function attachListeners(Session $session, bool $isMain): void
    {
        $session->on('method:Fetch.requestPaused', function (array $params) use ($session): void {
            $url = (string) ($params['request']['url'] ?? '');
            $reason = $this->filter->getBlockReason($url);

            if (null === $reason) {
                $this->send($session, 'Fetch.continueRequest', ['requestId' => $params['requestId']]);

                return;
            }

            $this->blocked[$url] = $reason;
            if ('Document' === ($params['resourceType'] ?? null) && ($params['frameId'] ?? null) === $this->mainFrameId) {
                $this->blockedNavigation ??= \sprintf('the page tried to navigate to "%s" which is not allowed: %s', self::shortUrl($url), $reason);
            }
            $this->logger?->warning('Chrome PDF: blocked request to "{url}": {reason}', ['url' => self::shortUrl($url), 'reason' => $reason]);
            $this->send($session, 'Fetch.failRequest', ['requestId' => $params['requestId'], 'errorReason' => 'AccessDenied']);
        });

        $session->on('method:Network.requestWillBeSent', function (array $params): void {
            $this->requestUrls[(string) $params['requestId']] = (string) ($params['request']['url'] ?? '');
        });

        $session->on('method:Network.responseReceived', function (array $params): void {
            $this->responses[(string) $params['requestId']] = [
                'url' => (string) ($params['response']['url'] ?? ''),
                'status' => (int) ($params['response']['status'] ?? 0),
                'type' => (string) ($params['type'] ?? ''),
            ];
        });

        $session->on('method:Network.loadingFailed', function (array $params): void {
            $requestId = (string) $params['requestId'];
            $this->loadingFailures[$requestId] = [
                'url' => $this->requestUrls[$requestId] ?? '(unknown URL)',
                'error' => (string) ($params['errorText'] ?? 'unknown error').(isset($params['blockedReason']) ? ' ['.$params['blockedReason'].']' : ''),
                'canceled' => !empty($params['canceled']),
                'type' => (string) ($params['type'] ?? ''),
            ];
        });

        $session->on('method:Runtime.exceptionThrown', function (array $params): void {
            $this->consoleExceptions[] = self::describeException($params['exceptionDetails'] ?? []);
        });

        $session->on('method:Target.attachedToTarget', function (array $params): void {
            $this->onAttachedToTarget($params);
        });

        $session->on('method:Target.detachedFromTarget', function (array $params): void {
            $sessionId = (string) ($params['sessionId'] ?? '');
            if (isset($this->childSessions[$sessionId])) {
                try {
                    $this->childSessions[$sessionId]->destroy();
                } catch (\Throwable) {
                }
                unset($this->childSessions[$sessionId]);
            }
        });

        if ($isMain) {
            $session->on('method:Page.lifecycleEvent', function (array $params): void {
                if (($params['frameId'] ?? null) === $this->mainFrameId) {
                    $this->lifecycle[(string) $params['loaderId']][(string) $params['name']] = true;
                }
            });

            // follows client-side redirects: the document to wait for is the last one committed in the main frame
            $session->on('method:Page.frameNavigated', function (array $params): void {
                $frame = $params['frame'] ?? [];
                if (($frame['id'] ?? null) === $this->mainFrameId && null !== $this->loaderId && isset($frame['loaderId'])) {
                    $this->loaderId = (string) $frame['loaderId'];
                }
            });

            $session->on('method:Inspector.targetCrashed', function (): void {
                $this->crashed = 'the page crashed (renderer process terminated)';
            });
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function onAttachedToTarget(array $params): void
    {
        $sessionId = (string) ($params['sessionId'] ?? '');
        $targetId = (string) ($params['targetInfo']['targetId'] ?? '');
        if ('' === $sessionId || '' === $targetId) {
            return;
        }

        try {
            $child = $this->connection->createSession($targetId, $sessionId);
        } catch (\Throwable $e) {
            $this->logger?->warning('Chrome PDF: unable to attach to child target: {message}', ['message' => $e->getMessage()]);

            return;
        }

        $this->childSessions[$sessionId] = $child;
        $this->attachListeners($child, false);

        // The target is paused until runIfWaitingForDebugger: install the filter first.
        // Commands a target type does not support (e.g. Fetch on some workers) just fail.
        $this->send($child, 'Network.enable');
        $this->send($child, 'Fetch.enable', ['patterns' => [['urlPattern' => '*', 'requestStage' => 'Request']]]);
        $this->send($child, 'Runtime.enable');
        $this->send($child, 'Target.setAutoAttach', ['autoAttach' => true, 'waitForDebuggerOnStart' => true, 'flatten' => true]);
        $this->send($child, 'Runtime.runIfWaitingForDebugger');
    }

    /**
     * Sends a command and waits for its result.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(string $method, array $params = [], int|null $timeout = null): array
    {
        $timeout ??= $this->timeout;
        $this->assertAlive($method);

        try {
            $response = $this->session->sendMessage(new Message($method, $params))->waitForResponse($timeout);
        } catch (OperationTimedOut $e) {
            throw new BrowserException(\sprintf('Timed out after %d ms waiting for Chrome to answer %s.', $timeout, $method), 0, $e);
        } catch (BrowserException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BrowserException(\sprintf('Chrome communication error during %s: %s', $method, $e->getMessage()), 0, $e);
        }

        if (!$response->isSuccessful()) {
            throw new BrowserException(\sprintf('Chrome rejected %s: %s', $method, $response->getErrorMessage()));
        }

        $this->discardPendingResponses();
        $result = $response->getData()['result'] ?? [];

        return \is_array($result) ? $result : [];
    }

    /**
     * Sends a command without waiting; its response is discarded later.
     *
     * @param array<string, mixed> $params
     */
    private function send(Session $session, string $method, array $params = []): void
    {
        try {
            $message = new Message($method, $params);
            $session->sendMessage($message);
            $this->pendingMessageIds[] = $message->getId();
        } catch (\Throwable $e) {
            $this->logger?->debug('Chrome PDF: unable to send {method}: {message}', ['method' => $method, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Responses of fire-and-forget commands are buffered by the connection forever unless consumed:
     * drop them to avoid a leak in long-running processes.
     */
    private function discardPendingResponses(): void
    {
        $remaining = [];
        foreach ($this->pendingMessageIds as $id) {
            if ($this->connection->hasResponseForId((string) $id)) {
                $this->connection->getResponseForId((string) $id);
            } else {
                $remaining[] = $id;
            }
        }
        $this->pendingMessageIds = $remaining;
    }

    /**
     * Processes CDP events until $condition is true.
     *
     * @param callable(): bool $condition
     */
    private function pump(callable $condition, int $timeoutMs, string $what): void
    {
        $deadline = self::now() + $timeoutMs;

        while (true) {
            try {
                $this->connection->readData();
            } catch (\Throwable $e) {
                throw new BrowserException(\sprintf('Chrome communication error while %s: %s', $what, $e->getMessage()), 0, $e);
            }
            $this->discardPendingResponses();

            if ($condition()) {
                return;
            }

            $this->assertAlive($what);

            $remaining = $deadline - self::now();
            if ($remaining <= 0) {
                throw new BrowserException(\sprintf('Timed out after %d ms %s.', $timeoutMs, $what));
            }

            if (!$this->connection->waitForData(min(0.05, $remaining / 1000))) {
                usleep(1000);
            }
        }
    }

    /**
     * Sleeps while still processing CDP events (request interception must keep running).
     */
    private function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        $until = self::now() + $milliseconds;
        $this->pump(static fn (): bool => self::now() >= $until, $milliseconds + 1000, 'sleeping');
    }

    private function assertAlive(string $what): void
    {
        if (null !== $this->blockedNavigation) {
            throw new BrowserException(\sprintf('Navigation blocked while %s: %s.', $what, $this->blockedNavigation));
        }

        if (null !== $this->crashed) {
            throw new BrowserException(\sprintf('Chrome error while %s: %s.', $what, $this->crashed));
        }

        if (!$this->connection->isConnected()) {
            throw new BrowserException(\sprintf('Lost the connection to Chrome while %s.', $what));
        }
    }

    /**
     * @param array<string, mixed> $details Runtime.ExceptionDetails
     */
    private static function describeException(array $details): string
    {
        $description = $details['exception']['description'] ?? $details['exception']['value'] ?? null;
        $text = \is_string($description) && '' !== $description ? strtok($description, "\n") : (string) ($details['text'] ?? 'Unknown exception');
        $location = isset($details['url']) && '' !== $details['url'] ? \sprintf(' at %s:%d:%d', self::shortUrl((string) $details['url']), (int) ($details['lineNumber'] ?? 0) + 1, (int) ($details['columnNumber'] ?? 0) + 1) : '';

        return $text.$location;
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $value): array
    {
        return array_values(array_map('intval', array_filter((array) $value, 'is_numeric')));
    }

    private static function shortUrl(string $url): string
    {
        return \strlen($url) > 200 ? substr($url, 0, 200).'...' : $url;
    }

    private static function now(): float
    {
        return hrtime(true) / 1e6;
    }
}
