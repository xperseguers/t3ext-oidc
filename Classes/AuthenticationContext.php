<?php

declare(strict_types=1);

namespace Causal\Oidc;

use Causal\Oidc\Security\JwtTrait;

class AuthenticationContext
{
    use JwtTrait;

    public string $requestId = '';
    public string $state = '';
    public string $loginUrl = '';
    public string $authorizationUrl = '';
    public string $redirectUrl = '';
    public ?string $codeVerifier = null;

    public function __construct(
        string $state,
        string $loginUrl,
        string $authorizationUrl,
        string $requestId = '',
        string $redirectUrl = '',
        ?string $codeVerifier = null
    ) {
        $this->state = $state;
        $this->loginUrl = $loginUrl;
        $this->authorizationUrl = $authorizationUrl;
        $this->requestId = $requestId;
        $this->redirectUrl = $redirectUrl;
        $this->codeVerifier = $codeVerifier;
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
