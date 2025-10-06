<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Security\JwtTrait;

class AuthenticationContext
{
    use JwtTrait;

    public function __construct(
        public readonly string $state,
        public readonly string $loginUrl,
        public readonly string $authorizationUrl,
        public readonly string $requestId,
        public readonly string $redirectUrl = '',
        public readonly ?string $codeVerifier = null,
    ) {}

    public static function fromJwt(string $cookieValue): self
    {
        $payload = self::decodeJwt($cookieValue, self::createSigningKeyFromEncryptionKey(static::class), true);
        return new self(...$payload);
    }

    public function toHashSignedJwt(): string
    {
        $payload = get_object_vars($this);
        return self::encodeHashSignedJwt($payload, self::createSigningKeyFromEncryptionKey(static::class));
    }
}
