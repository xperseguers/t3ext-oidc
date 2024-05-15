<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Service\OpenIdConnectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OauthCallback implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const COOKIE_NAME = 'oidc_context';
    protected const COOKIE_PREFIX = '';
    protected const SECURE_PREFIX = '__Secure-';

    protected OpenIdConnectService $openIdConnectService;

    public function __construct(OpenIdConnectService $openIdConnectService)
    {
        $this->openIdConnectService = $openIdConnectService;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * see https://github.com/thephpleague/oauth2-client
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authContext = $this->resolveAuthenticationContext($request);
        if ($authContext) {
            $this->openIdConnectService->setAuthenticationContext($authContext);
            $this->logger->debug('Authentication context is available', [
                'data' => $authContext,
            ]);
        }

        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? '';
        if (!$code) {
            $response = $handler->handle($request);
            return $this->enrichResponseWithCookie($request, $response);
        }
        if (!$authContext) {
            return (new Response())->withStatus(400, 'Missing OIDC authentication context');
        }

        // A code was supplied, we start the OIDC handling

        $this->logger->debug('Initiating the silent authentication');

        $state = $queryParams['state'] ?? '';
        if (!$state) {
            return (new Response())->withStatus(400, 'Invalid state');
        }
        if ($state !== $authContext->state) {
            $globalSettings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
            if (!$globalSettings['oidcDisableCSRFProtection']) {
                $this->logger->error('Invalid returning state detected', [
                    'expected' => $authContext->state,
                    'actual' => $state,
                ]);
                return (new Response())->withStatus(400, 'Invalid state');
            }
            $this->logger->info('State mismatch. Bypassing CSRF attack mitigation protection according to the extension configuration', [
                'expected' => $authContext->state,
                'actual' => $state,
            ]);
        }

        $loginUrl = $this->openIdConnectService->getFinalLoginUrl($code);

        $this->logger->info('Redirecting to login URL', ['url' => (string)$loginUrl]);

        return new RedirectResponse(GeneralUtility::locationHeaderUrl((string)$loginUrl), 303);
    }

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::resolveNoncePool (v12+)
     */
    protected function resolveAuthenticationContext(ServerRequestInterface $request): ?AuthenticationContext
    {
        $secure = $this->isHttps($request);
        // resolves cookie name dependent on whether TLS is used in request and uses `__Secure-` prefix,
        // see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#cookie_prefixes
        $securePrefix = $secure ? self::SECURE_PREFIX : '';
        $cookiePrefix = $securePrefix . self::COOKIE_PREFIX;
        $cookiePrefixLength = strlen($cookiePrefix);
        $cookies = array_filter(
            $request->getCookieParams(),
            static fn($name) => is_string($name) && str_starts_with($name, $cookiePrefix),
            ARRAY_FILTER_USE_KEY
        );
        foreach ($cookies as $name => $value) {
            $name = substr($name, $cookiePrefixLength);
            if ($name === self::COOKIE_NAME) {
                return AuthenticationContext::fromJwt($value);
            }
        }
        return null;
    }

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::enrichResponseWithCookie (v12+)
     */
    protected function enrichResponseWithCookie(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $authContext = $this->openIdConnectService->getAuthenticationContext();
        if (!$authContext) {
            return $response;
        }

        $secure = $this->isHttps($request);
        $normalizedParams = $request->getAttribute('normalizedParams');
        $path = $normalizedParams->getSitePath();
        $securePrefix = $secure ? self::SECURE_PREFIX : '';
        $cookiePrefix = $securePrefix . self::COOKIE_PREFIX;

        $createCookie = static fn(string $name, string $value, int $expire): Cookie => new Cookie(
            $name,
            $value,
            $expire,
            $path,
            null,
            $secure,
            true,
            false,
            Cookie::SAMESITE_LAX
        );

        $cookies = [];
        $cookies[] = $createCookie($cookiePrefix . self::COOKIE_NAME, $authContext->toHashSignedJwt(), 0);

        foreach ($cookies as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', (string)$cookie);
        }
        return $response;
    }

    protected function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();
    }
}
