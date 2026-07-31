<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

/** Extracts a useful API error while keeping log output free of common PII/secrets. */
final class ShipmentApiFailure
{
    /** Store the safe user message and structured operational context. */
    private function __construct(
        private string $message,
        private ?int $status,
        private ?string $apiErrorCode,
        private string $exceptionClass
    ) {
    }

    /** Parse a generated SDK or transport exception into one safe failure value. */
    public static function fromThrowable(\Throwable $exception): self
    {
        $message = $exception->getMessage();
        $status = self::status($exception);
        $code = null;

        if (method_exists($exception, 'getResponseBody')) {
            $body = json_decode((string) $exception->getResponseBody(), true);
            $error = $body['errors'][0] ?? null;

            if (is_array($error)) {
                [$message, $code, $errorStatus] = self::messageFromError($error, $message);
                $status ??= $errorStatus;
            }
        }

        return new self(self::sanitizeForAdmin($message), $status, $code, $exception::class);
    }

    /** Return the API-derived message that can be shown in the admin. */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Return PII-safe structured context for OpenCart's operational log.
     *
     * @return array{exception: string, status: int|null, api_error_code: string|null, api_error_message: string}
     */
    public function logDetails(): array
    {
        return [
            'exception' => $this->exceptionClass,
            'status' => $this->status,
            'api_error_code' => $this->apiErrorCode,
            'api_error_message' => self::sanitize($this->message),
        ];
    }

    /**
     * Read the known Problem Details or validation-error response shapes.
     *
     * @param array<string, mixed> $error
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private static function messageFromError(array $error, string $fallback): array
    {
        if (isset($error['message']) || isset($error['title'])) {
            $code = (string) ($error['code'] ?? $error['status'] ?? '');
            $text = (string) ($error['message'] ?? $error['title']);
            $detail = (string) ($error['detail'] ?? '');

            if ($detail !== '' && $detail !== $text) {
                $text .= ': ' . $detail;
            }

            $status = isset($error['status']) && is_numeric($error['status']) ? (int) $error['status'] : null;

            return [trim(($code !== '' ? '[' . $code . '] ' : '') . $text), $code ?: null, $status];
        }

        $messages = [];
        $codes = [];

        foreach ($error as $code => $detail) {
            if (is_array($detail) && !empty($detail['human'])) {
                $messages[] = $code . ': ' . implode('; ', (array) $detail['human']);
                $codes[] = (string) $code;
            }
        }

        return [
            $messages === [] ? $fallback : implode(' | ', $messages),
            $codes === [] ? null : implode(',', $codes),
            null,
        ];
    }

    /** Extract an HTTP status from the exception code or attached response. */
    private static function status(\Throwable $exception): ?int
    {
        $code = $exception->getCode();

        if (is_int($code) && $code >= 100 && $code <= 599) {
            return $code;
        }

        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                $status = (int) $response->getStatusCode();

                return $status >= 100 && $status <= 599 ? $status : null;
            }
        }

        return null;
    }

    /** Remove common PII, secrets and control characters from a log message. */
    private static function sanitize(string $message): string
    {
        $message = self::sanitizeForAdmin($message);

        return mb_substr(trim($message), 0, 500);
    }

    /** Preserve API validation guidance while masking concrete personal data. */
    private static function sanitizeForAdmin(string $message): string
    {
        $message = self::redactSecrets($message);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? '';
        $message = preg_replace('/\+?[0-9][0-9 .()\/-]{6,}[0-9]/', '[phone]', $message) ?? '';
        $message = preg_replace(
            '/(?<!invalid )(\b(?:name|email|phone|address|street|recipient)\b\s*[:=]\s*)'
                . '(?!(?:should|must|is|was|has|cannot|required|missing|invalid|the|het|de|il|lo|la)\b)'
                . '[^;|}]+/iu',
            '$1[redacted]',
            $message
        ) ?? '';
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', $message) ?? '';

        return mb_substr(trim($message), 0, 1000);
    }

    /** Remove credentials while retaining useful API validation details for the admin. */
    private static function redactSecrets(string $message): string
    {
        return preg_replace(
            '/(?:\bBearer\s+|(?:api[_ -]?key[=:]\s*|authorization[=:]\s*)(?:Bearer\s+)?)[^\s,;]+/i',
            '[redacted]',
            $message
        ) ?? '';
    }
}
