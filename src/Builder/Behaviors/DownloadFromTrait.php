<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Exception\DownloadFromException;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Formatter\PathHelper;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;

/**
 * @package Behavior\\DownloadFrom
 */
trait DownloadFromTrait
{
    use LoggerAwareTrait;
    use ServiceMethodsSubscriberTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * Downloads remote files (in parallel) right before the generation and adds them as assets (markdown files are
     * added to the markdown files of the Markdown builders). Each file is stored under the filename given by the
     * Content-Disposition header of the response or, if missing, the basename of the URL: reference it by that name.
     * Requires symfony/http-client. Downloaded files are deleted once the generation is over.
     *
     * @param list<array{url: string, extraHttpHeaders?: array<string, string>}> $downloadFrom
     *
     * @example downloadFrom([['url' => 'https://example.com/logo.png', 'extraHttpHeaders' => ['Authorization' => 'Bearer token']]])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('download_from', prototype: 'array', children: [
        new ScalarNodeBuilder('url', required: true, restrictTo: 'string'),
        new ArrayNodeBuilder('extraHttpHeaders', normalizeKeys: false, useAttributeAsKey: 'name', prototype: 'variable'),
    ]))]
    public function downloadFrom(array $downloadFrom): static
    {
        ValidatorFactory::download($downloadFrom);

        if ([] === $downloadFrom) {
            $this->getBodyBag()->unset('downloadFrom');

            return $this;
        }

        $value = $this->getBodyBag()->get('downloadFrom', []);

        foreach ($downloadFrom as $file) {
            $entry = ['url' => $file['url']];
            if ([] !== ($file['extraHttpHeaders'] ?? [])) {
                $entry['extraHttpHeaders'] = array_map(static fn ($v): string => (string) $v, $file['extraHttpHeaders']);
            }

            $value[$file['url']] = $entry;
        }

        $this->getBodyBag()->set('downloadFrom', $value);

        return $this;
    }

    #[SubscribedService('http_client', nullable: true)]
    protected function getHttpClient(): HttpClientInterface
    {
        if (
            !$this->container->has('http_client')
            || !($httpClient = $this->container->get('http_client')) instanceof HttpClientInterface
        ) {
            throw new LogicException('An HTTP client is required to use "downloadFrom()". Try to run "composer require symfony/http-client" (and enable framework.http_client).');
        }

        return $httpClient;
    }

    #[SubscribedService('.daif_chrome_pdf.download_guard', nullable: true)]
    protected function getDownloadGuard(): DownloadGuard
    {
        if (
            !$this->container->has('.daif_chrome_pdf.download_guard')
            || !($guard = $this->container->get('.daif_chrome_pdf.download_guard')) instanceof DownloadGuard
        ) {
            throw new LogicException('The ".daif_chrome_pdf.download_guard" service is missing: downloadFrom() cannot apply the allow/deny lists.');
        }

        return $guard;
    }

    /**
     * Downloads the files declared with downloadFrom() into a temporary directory and registers them.
     * The returned closure removes the temporary directory.
     *
     * @throws DownloadFromException
     */
    protected function downloadRemoteFiles(): \Closure|null
    {
        /** @var array<string, array{url: string, extraHttpHeaders?: array<string, string>}> $entries */
        $entries = $this->getBodyBag()->get('downloadFrom', []);
        if ([] === $entries) {
            return null;
        }

        $guard = $this->getDownloadGuard();
        foreach ($entries as $url => $entry) {
            $guard->assertAllowed($url);
        }

        $httpClient = $this->getHttpClient();
        $filesystem = new Filesystem();
        $directory = $guard->getTempDir().\DIRECTORY_SEPARATOR.'chrome_pdf_download_'.bin2hex(random_bytes(8));
        // downloads may come from authenticated requests
        $filesystem->mkdir($directory, 0o700);

        $logger = $this->getLogger();
        $bag = $this->getBodyBag();
        $originalAssets = $bag->get('assets');
        $originalFiles = $bag->get('files');
        // Restores the user's assets/files so the builder can be generated again without stale temporary paths.
        $cleanup = static function () use ($filesystem, $directory, $logger, $bag, $originalAssets, $originalFiles): void {
            null === $originalAssets ? $bag->unset('assets') : $bag->set('assets', $originalAssets);
            null === $originalFiles ? $bag->unset('files') : $bag->set('files', $originalFiles);

            try {
                $filesystem->remove($directory);
            } catch (\Throwable $e) {
                $logger?->warning('Could not remove the downloadFrom temporary directory "{directory}": {message}', ['directory' => $directory, 'message' => $e->getMessage()]);
            }
        };

        try {
            $responses = [];
            foreach ($entries as $url => $entry) {
                // Requests are lazy: they are all sent concurrently.
                $responses[$url] = $httpClient->request('GET', $url, [
                    'headers' => $entry['extraHttpHeaders'] ?? [],
                    // A redirect could point outside the allow list; 3xx responses are reported as errors below.
                    'max_redirects' => 0,
                ]);
            }

            $assets = $this->getBodyBag()->get('assets', []);
            $markdownFiles = $this->getBodyBag()->get('files');
            $acceptsMarkdown = method_exists($this, 'getAllowedFilesExtensions') && \in_array('md', $this->getAllowedFilesExtensions(), true);
            $names = [];
            $index = 0;

            foreach ($responses as $url => $response) {
                ++$index;
                try {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode >= 300) {
                        throw new DownloadFromException(\sprintf('Could not download "%s": the server responded with HTTP status %d.', $url, $statusCode));
                    }

                    $filename = self::guessDownloadedFilename($url, $response->getHeaders()['content-disposition'][0] ?? null) ?: 'download_'.$index;
                    if (isset($names[$filename])) {
                        throw new DownloadFromException(\sprintf('The downloadFrom URLs "%s" and "%s" both resolve to the filename "%s". Make the server send distinct Content-Disposition filenames.', $names[$filename], $url, $filename));
                    }
                    $names[$filename] = $url;

                    $target = $directory.\DIRECTORY_SEPARATOR.$filename;
                    $handle = @fopen($target, 'w') ?: throw new DownloadFromException(\sprintf('Could not write the downloaded file "%s".', $target));
                    try {
                        foreach ($httpClient->stream($response) as $chunk) {
                            fwrite($handle, $chunk->getContent());
                        }
                    } finally {
                        fclose($handle);
                    }
                } catch (HttpClientExceptionInterface $e) {
                    throw new DownloadFromException(\sprintf('Could not download "%s": %s', $url, $e->getMessage()), previous: $e);
                }

                $fileInfo = new \SplFileInfo($target);
                if ($acceptsMarkdown && 'md' === PathHelper::extension($filename)) {
                    $markdownFiles ??= [];
                    $markdownFiles[$target] = $fileInfo;
                } else {
                    // Keyed by the temp path so the file is copied under the downloaded filename, not the URL basename.
                    $assets[$target] = $fileInfo;
                }

                $logger?->debug('Downloaded "{url}" as "{filename}".', ['url' => $url, 'filename' => $filename]);
            }

            if ([] !== $assets) {
                $this->getBodyBag()->set('assets', $assets);
            }
            if (null !== $markdownFiles) {
                $this->getBodyBag()->set('files', $markdownFiles);
            }
        } catch (\Throwable $e) {
            $cleanup();

            throw $e;
        }

        return $cleanup;
    }

    private static function guessDownloadedFilename(string $url, string|null $contentDisposition): string
    {
        $filename = '';

        if (null !== $contentDisposition && '' !== $contentDisposition) {
            $parameters = array_change_key_case(HeaderUtils::combine(HeaderUtils::split($contentDisposition, ';=')));

            if (\is_string($parameters['filename*'] ?? null) && preg_match("/^[^']*'[^']*'(.+)$/", $parameters['filename*'], $matches)) {
                $filename = rawurldecode($matches[1]);
            } elseif (\is_string($parameters['filename'] ?? null)) {
                $filename = $parameters['filename'];
            }
        }

        if ('' === $filename) {
            $filename = PathHelper::basename($url);
        }

        // Never trust remote names: keep the basename only and strip characters forbidden on Windows/Unix.
        $filename = (string) preg_replace('/[\x00-\x1F<>:"\/\\\\|?*]/', '_', PathHelper::basename($filename));

        return trim($filename, " .\t\n\r\0\x0B");
    }
}
