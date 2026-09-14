<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

final class LicenseSigner
{
    public function sign(array $payload, string $kid, string $privateKey): string
    {
        $privateKey = LicenseDocument::base64UrlDecode($privateKey) ?? base64_decode($privateKey, true) ?: $privateKey;
        if (strlen($privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('The Ed25519 private key must be 64 bytes.');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $kid)) {
            throw new \InvalidArgumentException('The license key id is invalid.');
        }

        $payloadBytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $payloadEncoded = LicenseDocument::base64UrlEncode($payloadBytes);
        $message = 'seviaflow-license-v1.'.$kid.'.'.$payloadEncoded;
        $signature = sodium_crypto_sign_detached($message, $privateKey);

        return json_encode([
            'kid' => $kid,
            'payload_b64url' => $payloadEncoded,
            'signature_b64url' => LicenseDocument::base64UrlEncode($signature),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
