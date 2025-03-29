<?php

declare(strict_types=1);

namespace Causal\Oidc\Http;

use Causal\Oidc\AuthenticationContext;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Http\NormalizedParams;

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

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::resolveNoncePool (v12+)
     */
    public function resolveAuthenticationContext(ServerRequestInterface $request): ?AuthenticationContext
    {
        $secure = $this->isHttps($request);
        foreach ($request->getCookieParams() as $name => $value) {
            $authenticationContext = $this->resolveCookieToAuthenticationContext($secure, $name, $value);
            if (isset($authenticationContext)) {
                return $authenticationContext;
            }
        }

        return null;
    }

    protected function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();
    }

    protected function resolveCookieToAuthenticationContext(
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
