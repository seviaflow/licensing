<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

use JsonException;

final class LicenseDocument
{
    public static function encode(array $payload, string $kid, string $signature): string
    {
        try {
            return json_encode([
                'kid' => $kid,
                'payload_b64url' => self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'signature_b64url' => self::base64UrlEncode($signature),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('License payload cannot be encoded.', 0, $exception);
        }
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return is_string($decoded) ? $decoded : null;
    }
}
