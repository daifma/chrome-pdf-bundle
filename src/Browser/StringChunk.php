<?php

namespace Daif\ChromePdfBundle\Browser;

use Symfony\Contracts\HttpClient\ChunkInterface;

final class StringChunk implements ChunkInterface
{
    public function __construct(
        private readonly string $content,
    ) {
    }

    public function isTimeout(): bool
    {
        return false;
    }

    public function isFirst(): bool
    {
        return true;
    }

    public function isLast(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInformationalStatus(): array|null
    {
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getOffset(): int
    {
        return 0;
    }

    public function getError(): string|null
    {
        return null;
    }
}
