<?php

namespace Daif\ChromePdfBundle\Builder\Util;

use Daif\ChromePdfBundle\Browser\RequestFilter;
use Daif\ChromePdfBundle\Exception\DownloadFromException;

/**
 * Applies the bundle's allow/deny lists and temp_dir to downloadFrom(), which runs in PHP and never reaches Chrome's filter.
 *
 * @internal
 */
final class DownloadGuard
{
    private readonly RequestFilter $filter;

    /**
     * @param list<string> $allowList
     * @param list<string> $denyList
     */
    public function __construct(
        array $allowList = [],
        array $denyList = [],
        private readonly string|null $tempDir = null,
    ) {
        $this->filter = new RequestFilter(null, $allowList, $denyList);
    }

    /**
     * @throws DownloadFromException
     */
    public function assertAllowed(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new DownloadFromException(\sprintf('Could not download "%s": only http and https URLs are supported.', $url));
        }

        $reason = $this->filter->getBlockReason($url);
        if (null !== $reason) {
            throw new DownloadFromException(\sprintf('Could not download "%s": %s.', $url, $reason));
        }
    }

    public function getTempDir(): string
    {
        return rtrim($this->tempDir ?? sys_get_temp_dir(), '/\\');
    }
}
