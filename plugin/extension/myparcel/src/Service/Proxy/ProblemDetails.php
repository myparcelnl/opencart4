<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Proxy;

/**
 * Response helper for local proxy rejections.
 */
final class ProblemDetails implements \JsonSerializable
{
    public const CONTENT_TYPE = 'application/problem+json; charset=utf-8';

    private int $status;
    private string $title;
    private string $detail;

    /** Create one problem-details response body. */
    public function __construct(int $status, string $title, string $detail)
    {
        $this->status = $status;
        $this->title = $title;
        $this->detail = $detail;
    }

    /**
     * Build a problem document, mapping the HTTP status to a human-readable title.
     */
    public static function fromStatus(int $status, string $detail): self
    {
        $titles = [
            400 => 'Invalid Request',
            403 => 'Forbidden',
            405 => 'Method Not Allowed',
            413 => 'Content Too Large',
            502 => 'Bad Gateway',
        ];

        return new self($status, $titles[$status] ?? 'Error', $detail);
    }

    /**
     * Encode the problem document as a JSON string.
     */
    public function toJsonString(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * Return the RFC 7807-compatible response fields.
     *
     * @return array<string, int|string|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'about:blank',
            'status' => $this->status,
            'title' => $this->title,
            'detail' => $this->detail,
        ];
    }
}
