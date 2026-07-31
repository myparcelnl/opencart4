<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Proxy;

/**
 * Plain response value object for forwarded and locally rejected proxy calls.
 */
final class ProxyResponse
{
    public int $status;

    /** @var array<string, string> */
    public array $headers;

    public string $body;

    /**
     * Create a response that the storefront proxy can emit unchanged.
     *
     * @param array<string, string> $headers
     */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Return a copy with extra or replacement headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, array_merge($this->headers, $headers), $this->body);
    }
}
