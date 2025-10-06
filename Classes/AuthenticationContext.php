<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Security\JwtTrait;

class AuthenticationContext
{
    use JwtTrait;

    public function __construct(
        protected string $state,
        protected string $loginUrl,
        protected string $authorizationUrl,
        protected string $requestId,
        protected string $redirectUrl = '',
        public ?string $codeVerifier = null,
    ) {
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function getLoginUrl(): string
    {
        return $this->loginUrl;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

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
