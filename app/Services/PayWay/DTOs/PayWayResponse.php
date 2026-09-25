<?php

namespace Modules\Payment\Services\PayWay\DTOs;

use ArrayAccess;

/**
 * PayWay API response wrapper.
 *
 * Implements ArrayAccess for backward compatibility with array-based access.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class PayWayResponse implements ArrayAccess
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $error,
        public readonly ?array $data,
    ) {}

    public static function success(array $data): self
    {
        return new self(
            success: true,
            error: null,
            data: $data,
        );
    }

    public static function error(string $message, ?array $data = null): self
    {
        return new self(
            success: false,
            error: $message,
            data: $data,
        );
    }

    /**
     * Get a value from the response data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Check if response has a specific key.
     */
    public function has(string $key): bool
    {
        return data_get($this->data, $key) !== null;
    }

    /**
     * Get the status code from response.
     */
    public function getStatusCode(): ?string
    {
        return $this->get('status.code');
    }

    /**
     * Get the status message from response.
     */
    public function getStatusMessage(): ?string
    {
        return $this->get('status.message');
    }

    /**
     * Check if the request was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the request failed.
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get response data.
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Convert to array for backward compatibility.
     */
    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    // ========================================
    // ArrayAccess implementation for backward compatibility
    // ========================================

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['success', 'error', 'data'], true);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'success' => $this->success,
            'error' => $this->error,
            'data' => $this->data,
            default => null,
        };
    }

    /**
     * @param string $offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('PayWayResponse is immutable');
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('PayWayResponse is immutable');
    }
}
