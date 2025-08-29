<?php

declare(strict_types=1);

namespace Causal\Oidc\Http;

use Causal\Oidc\AuthenticationContext;
use Symfony\Component\HttpFoundation\Cookie;

class CookieService
{
    protected const SECURE_PREFIX = '__Secure-';
    protected const COOKIE_NAME = 'oidc_context';

    public function getCookieForAuthenticationContext(
        AuthenticationContext $authenticationContext,
        bool $secure,
        string $path = '/',
    ): Cookie {
        return new Cookie(
            $this->getCookieName($secure),
            $authenticationContext->toHashSignedJwt(),
            0,
            $path,
            null,
            $secure,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public function resolveCookieToAuthenticationContext(
        bool $secure,
        string $name,
        string $value
    ): ?AuthenticationContext {
        $cookieName = $this->getCookieName($secure);
        if ($name !== $cookieName) {
            return null;
        }

        return AuthenticationContext::fromJwt($value);
    }

    protected function getCookieName(bool $secure): string
    {
        $cookiePrefix = $secure ? self::SECURE_PREFIX : '';
        $cookieName = $cookiePrefix . self::COOKIE_NAME;
        return $cookieName;
    }
}
