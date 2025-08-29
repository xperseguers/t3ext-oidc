<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Http\CookieService;
use Causal\Oidc\LoginProvider\OidcLoginProvider;
use Causal\Oidc\Service\OpenIdConnectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function PHPUnit\Framework\isNull;

class OauthCallback implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected OpenIdConnectService $openIdConnectService,
        protected CookieService $cookieService,
    ) {
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
        $route = $request->getAttribute('route');
        if (!is_null($route)) {
            return $handler->handle($request);
        }

        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? '';
        if (!$code) {
            return $this->enrichResponseWithCookie($request, $handler->handle($request));
        }

        $authContext = $this->resolveAuthenticationContext($request);
        if ($authContext) {
            $this->openIdConnectService->setAuthenticationContext($authContext);
            $this->logger->debug('Authentication context is available', ['data' => $authContext]);
        } else {
            return (new Response())->withStatus(400, 'Missing OIDC authentication context');
        }

        // A code was supplied, we start the OIDC handling

        $this->logger->debug('Initiating the silent authentication');

        $state = $queryParams['state'] ?? '';
        if (!$state) {
            return (new Response())->withStatus(400, 'Invalid state');
        }
        if ($state !== $authContext->getState()) {
            $globalSettings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
            if (!$globalSettings['oidcDisableCSRFProtection']) {
                $this->logger->error('Invalid returning state detected', [
                    'expected' => $authContext->getState(),
                    'actual' => $state,
                ]);
                return (new Response())->withStatus(400, 'Invalid state');
            }
            $this->logger->info('State mismatch. Bypassing CSRF attack mitigation protection according to the extension configuration', [
                'expected' => $authContext->getState(),
                'actual' => $state,
            ]);
        }

        $loginUrl = $this->openIdConnectService->getFinalLoginUrl($code);

        $this->logger->info('Redirecting to login URL', ['url' => (string)$loginUrl]);

        return new RedirectResponse(GeneralUtility::locationHeaderUrl((string)$loginUrl), 303);
    }

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::resolveNoncePool
     */
    protected function resolveAuthenticationContext(ServerRequestInterface $request): ?AuthenticationContext
    {
        $secure = $this->isHttps($request);
        foreach ($request->getCookieParams() as $name => $value) {
            $authenticationContext = $this->cookieService->resolveCookieToAuthenticationContext($secure, $name, $value);
            if (isset($authenticationContext)) {
                return $authenticationContext;
            }
        }

        return null;
    }

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::enrichResponseWithCookie
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

        $cookie = $this->cookieService->getCookieForAuthenticationContext($authContext, $secure, $path);

        return $response->withAddedHeader('Set-Cookie', (string)$cookie);
    }

    /**
     * @see \TYPO3\CMS\Core\Middleware\RequestTokenMiddleware::enrichResponseWithCookie
     */
    protected function enrichRequestWithLoginParams(ServerRequestInterface $request, $code): ServerRequestInterface
    {
        $loginUrlParams = ['logintype' => 'login'];
        if ($request->getUri()->getPath() === '/typo3/login') {
            $loginUrlParams = [
                'login_status' => 'login',
                'loginProvider' => OidcLoginProvider::IDENTIFIER,
            ];
        }

        $loginUrlParams['tx_oidc'] = ['code' => $code];

        if ($this->openIdConnectService->getAuthenticationContext()->redirectUrl && !str_contains($this->openIdConnectService->getAuthenticationContext()->getLoginUrl(), 'redirect_url=')) {
            $loginUrlParams['redirect_url'] = $this->openIdConnectService->getAuthenticationContext()->redirectUrl;
        }

        return $request->withQueryParams(array_merge($request->getQueryParams(), $loginUrlParams));
    }

    protected function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();
    }
}
