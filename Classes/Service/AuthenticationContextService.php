<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use Causal\Oidc\AuthenticationContext;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Http\NormalizedParams;

class AuthenticationContextService
{
    protected const SECURE_PREFIX = '__Secure-';
    protected const COOKIE_NAME = 'oidc_context';

    /**
     * Produce a cookie that stores the authentication context
     * while the client visits the identity provider.
     */
    public function getCookieForAuthenticationContext(AuthenticationContext $authenticationContext): Cookie
    {
        return new Cookie(
            $this->getCookieName($authenticationContext->secureContext),
            $authenticationContext->toHashSignedJwt(),
            0,
            '/',
            null,
            $authenticationContext->secureContext,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    /**
     * Find an oidc authentication context (cookie) in the given request
     */
    public function resolveAuthenticationContext(ServerRequestInterface $request): ?AuthenticationContext
    {
        $isHttps = $this->isHttps($request);
        $cookie = $request->getCookieParams()[$this->getCookieName($isHttps)] ?? null;
        if (!isset($cookie)) {
            return null;
        }

        $authenticationContext = AuthenticationContext::fromJwt($cookie);

        if ($authenticationContext->secureContext && !$isHttps) {
            return null;
        }

        return $authenticationContext;
    }

    protected function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();
    }

    protected function getCookieName(bool $secureContext): string
    {
        $cookiePrefix = $secureContext ? self::SECURE_PREFIX : '';
        return $cookiePrefix . self::COOKIE_NAME;
    }
}
