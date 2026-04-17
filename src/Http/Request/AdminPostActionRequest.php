<?php
declare(strict_types=1);

namespace PPStudio\Http\Request;

final class AdminPostActionRequest
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $session
     */
    public function __construct(
        private array $server,
        private array $post,
        private array $files,
        private array $session
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $session
     */
    public static function fromGlobals(array $server, array $post, array $files, array $session): self
    {
        return new self($server, $post, $files, $session);
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

    public function hasPostKey(string $key): bool
    {
        return isset($this->post[$key]);
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
}
