<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminPageState
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        private array $values
    ) {
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function merge(array $values): self
    {
        return new self(array_replace($this->values, $values));
    }
}
