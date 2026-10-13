<?php

namespace Daif\ChromePdfBundle\Builder;

class BodyBag
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {
    }

    /**
     * Returns every value. Values are plain data (scalars, arrays, enums, value objects) so the bag can be serialized.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    public function set(string $name, mixed $value): static
    {
        $this->data[$name] = $value;

        return $this;
    }

    public function unset(string $name): static
    {
        unset($this->data[$name]);

        return $this;
    }
}
