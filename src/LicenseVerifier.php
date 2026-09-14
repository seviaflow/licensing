<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

use Carbon\CarbonImmutable;
use Throwable;

final class LicenseVerifier
{
    public function verify(?string $document, array $context = []): LicenseDecision
    {
        if (! is_string($document) || trim($document) === '') {
            return new LicenseDecision(false, 'module_license_required', 'No signed entitlement is installed.');
        }

        try {
            $envelope = json_decode($document, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return new LicenseDecision(false, 'license_invalid_document', 'The signed entitlement is not valid JSON.');
        }

        if (! is_array($envelope)
            || ! is_string($envelope['kid'] ?? null)
            || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $envelope['kid'])
            || ! is_string($envelope['payload_b64url'] ?? null)
            || ! is_string($envelope['signature_b64url'] ?? null)) {
            return new LicenseDecision(false, 'license_invalid_document', 'The signed entitlement envelope is invalid.');
        }

        $payloadBytes = LicenseDocument::base64UrlDecode($envelope['payload_b64url']);
        $signature = LicenseDocument::base64UrlDecode($envelope['signature_b64url']);
        if ($payloadBytes === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return new LicenseDecision(false, 'license_invalid_document', 'The signed entitlement encoding is invalid.');
        }

        $trusted = config('seviaflow-licensing.trusted_keys', []);
        $publicKey = is_array($trusted) ? ($trusted[$envelope['kid']] ?? null) : null;
        if (! is_string($publicKey)) {
            return new LicenseDecision(false, 'license_untrusted_key', 'The entitlement key is not trusted.');
        }
        $publicKey = LicenseDocument::base64UrlDecode($publicKey) ?? base64_decode($publicKey, true) ?: $publicKey;
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || ! sodium_crypto_sign_verify_detached($signature, 'seviaflow-license-v1.'.$envelope['kid'].'.'.$envelope['payload_b64url'], $publicKey)) {
            return new LicenseDecision(false, 'license_invalid_signature', 'The entitlement signature is invalid.');
        }

        try {
            $payload = json_decode($payloadBytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return new LicenseDecision(false, 'license_invalid_payload', 'The entitlement payload is invalid.');
        }
        if (! is_array($payload)) {
            return new LicenseDecision(false, 'license_invalid_payload', 'The entitlement payload is invalid.');
        }

        foreach (['schema_version', 'issuer', 'audience', 'activation_id', 'installation_uuid', 'environment', 'products', 'generation', 'issued_at', 'offline_until'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return new LicenseDecision(false, 'license_invalid_payload', 'The entitlement payload is incomplete.');
            }
        }
        if ($payload['schema_version'] !== 1
            || $payload['issuer'] !== config('seviaflow-licensing.issuer')
            || $payload['audience'] !== config('seviaflow-licensing.audience')
            || ! is_int($payload['generation'])
            || ! is_array($payload['products'])) {
            return new LicenseDecision(false, 'license_invalid_payload', 'The entitlement policy is invalid.');
        }

        foreach (['installation_uuid', 'environment'] as $field) {
            if (isset($context[$field]) && (string) $context[$field] !== (string) $payload[$field]) {
                return new LicenseDecision(false, 'license_scope_mismatch', 'The entitlement is for another installation.');
            }
        }
        if (isset($context['activation_id']) && (string) $context['activation_id'] !== (string) $payload['activation_id']) {
            return new LicenseDecision(false, 'license_scope_mismatch', 'The entitlement activation does not match.');
        }

        if (isset($context['host']) && ! $this->hostAllowed((string) $context['host'], $payload['hosts'] ?? [])) {
            return new LicenseDecision(false, 'license_host_mismatch', 'The entitlement does not cover this host.');
        }

        $now = CarbonImmutable::now();
        $clockSkew = max(0, (int) config('seviaflow-licensing.clock_skew_seconds', 300));
        if ($this->timestamp($payload['issued_at']) > $now->addSeconds($clockSkew)->timestamp
            || $this->timestamp($payload['offline_until']) < $now->subSeconds($clockSkew)->timestamp) {
            return new LicenseDecision(false, 'license_expired', 'The entitlement is no longer valid.');
        }

        $product = (string) ($context['product'] ?? '');
        $productGrant = $product !== '' ? ($payload['products'][$product] ?? null) : null;
        if ($product !== '' && ! is_array($productGrant)) {
            return new LicenseDecision(false, 'module_license_required', 'The entitlement does not include this module.');
        }
        if (is_array($productGrant) && isset($productGrant['use_until']) && $this->timestamp($productGrant['use_until']) < $now->timestamp) {
            return new LicenseDecision(false, 'module_entitlement_expired', 'The module entitlement has expired.');
        }

        return new LicenseDecision(true, 'license_valid', 'The module entitlement is valid.', $payload);
    }

    private function timestamp(mixed $value): int
    {
        try {
            return CarbonImmutable::parse((string) $value)->timestamp;
        } catch (Throwable) {
            return 0;
        }
    }

    private function hostAllowed(string $host, mixed $hosts): bool
    {
        if (! is_array($hosts)) {
            return false;
        }
        $host = strtolower(rtrim($host, '.'));
        foreach ($hosts as $allowed) {
            if (! is_string($allowed)) {
                continue;
            }
            $allowed = strtolower(rtrim($allowed, '.'));
            if ($host === $allowed) {
                return true;
            }
            if (str_starts_with($allowed, '*.')) {
                $suffix = substr($allowed, 1);
                if ($suffix !== '' && str_ends_with('.'.$host, $suffix) && substr_count($host, '.') > substr_count($suffix, '.')) {
                    return true;
                }
            }
        }

        return false;
    }
}
