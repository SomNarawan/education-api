<?php

namespace App\Services;

/**
 * Answer of one check against the central systems. "rejected" means they
 * answered no (HTTP 4xx or status "fail"); "unavailable" means there was no
 * usable answer (5xx, timeout, network) and must never end a session.
 */
final class PortalAuthResult
{
    public const OK = 'ok';

    public const REJECTED = 'rejected';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public readonly string $status,
        public readonly mixed $value = null,
        public readonly string $reason = '',
    ) {}

    public static function ok(mixed $value = null): self
    {
        return new self(self::OK, $value);
    }

    public static function rejected(string $reason): self
    {
        return new self(self::REJECTED, reason: $reason);
    }

    public static function unavailable(string $reason): self
    {
        return new self(self::UNAVAILABLE, reason: $reason);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    public function isRejected(): bool
    {
        return $this->status === self::REJECTED;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::UNAVAILABLE;
    }
}
