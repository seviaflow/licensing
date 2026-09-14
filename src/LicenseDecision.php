<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

final readonly class LicenseDecision
{
    public function __construct(
        public bool $allowed,
        public string $code,
        public string $message,
        public ?array $payload = null,
    ) {}

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'code' => $this->code,
            'message' => $this->message,
            'generation' => is_array($this->payload) ? ($this->payload['generation'] ?? null) : null,
            'use_until' => is_array($this->payload) ? ($this->payload['use_until'] ?? null) : null,
            'updates_until' => is_array($this->payload) ? ($this->payload['updates_until'] ?? null) : null,
        ];
    }
}
