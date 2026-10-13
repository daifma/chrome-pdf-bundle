<?php

namespace Daif\ChromePdfBundle\Tests\Twig\Fixtures;

use Daif\ChromePdfBundle\Builder\AssetPathResolverInterface;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;

final class ResolvingBuilder implements BuilderAssetInterface, AssetPathResolverInterface
{
    /** @var list<string> */
    public array $added = [];

    /**
     * @param array<string, string> $map asset path => resolved path
     */
    public function __construct(private readonly array $map)
    {
    }

    public function addAsset(string $path): static
    {
        $this->added[] = $path;

        return $this;
    }

    public function resolveAssetPath(string $path): string
    {
        return $this->map[$path] ?? throw new \RuntimeException('Unexpected asset '.$path);
    }
}
