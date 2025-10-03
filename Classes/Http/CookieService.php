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
        bool $secureContext,
        string $path = '/',
    ): Cookie {
        return new Cookie(
            $this->getCookieName($secureContext),
            $authenticationContext->toHashSignedJwt(),
            0,
            $path,
            null,
            $secureContext,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public function resolveCookieToAuthenticationContext(
        bool $secureContext,
        string $cookieNameFromRequest,
        string $cookieValueFromRequest
    ): ?AuthenticationContext {
        $cookieName = $this->getCookieName($secureContext);
        if ($cookieNameFromRequest !== $cookieName) {
            return null;
        }

        return AuthenticationContext::fromJwt($cookieValueFromRequest);
    }

    protected function getCookieName(bool $secureContext): string
    {
        $cookiePrefix = $secureContext ? self::SECURE_PREFIX : '';
        return $cookiePrefix . self::COOKIE_NAME;
    }
}
