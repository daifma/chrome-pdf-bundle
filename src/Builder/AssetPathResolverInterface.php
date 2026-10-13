<?php

namespace Daif\ChromePdfBundle\Builder;

/**
 * Implemented by builders able to resolve an asset path (relative to the configured assets directories)
 * into an absolute path or URL. Used by the Twig runtime to inline assets as data: URIs in headers/footers.
 */
interface AssetPathResolverInterface
{
    /**
     * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException if the asset cannot be found
     */
    public function resolveAssetPath(string $path): string;
}
