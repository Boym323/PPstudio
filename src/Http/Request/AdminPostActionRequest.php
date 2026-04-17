<?php
declare(strict_types=1);

namespace PPStudio\Http\Request;

final class AdminPostActionRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $session
     */
    public function __construct(
        private array $query,
        private array $server,
        private array $post,
        private array $files,
        private array $session
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $session
     */
    public static function fromHttpGlobals(array $server, array $query, array $post, array $files, array $session): self
    {
        return new self($query, $server, $post, $files, $session);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function method(): string
    {
        return (string) ($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }

    public function isPostTooLarge(string $postMaxSize): bool
    {
        $contentLength = $this->contentLength();

        return $this->isPost()
            && $contentLength > 0
            && $this->post === []
            && $this->files === []
            && $contentLength > $this->iniSizeToBytes($postMaxSize);
    }

    public function hasPostKey(string $key): bool
    {
        return isset($this->post[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function server(): array
    {
        return $this->server;
    }

    /**
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return $this->post;
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * @return array<string, mixed>
     */
    public function session(): array
    {
        return $this->session;
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
