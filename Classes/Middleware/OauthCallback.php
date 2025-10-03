<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\Http\CookieService;
use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\OpenIdConnectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OauthCallback implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected OpenIdConnectService $openIdConnectService,
        protected CookieService $cookieService,
        protected OidcConfiguration $settings
    ) {}

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * see https://github.com/thephpleague/oauth2-client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }

        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? '';
        if (!$code) {
            return $handler->handle($request);
        }

        // A code was supplied, we start the OIDC handling
        $authContext = $this->cookieService->resolveAuthenticationContext($request);
        if ($authContext) {
            $this->logger->debug('Authentication context is available', ['data' => $authContext]);
        }

        if (!$authContext) {
            return (new Response())->withStatus(400, 'Missing OIDC authentication context');
        }

        $this->logger->debug('Initiating the silent authentication');

        $state = $queryParams['state'] ?? '';
        if (!$state) {
            return (new Response())->withStatus(400, 'Invalid state');
        }
        if ($state !== $authContext->getState()) {
            if (!$this->settings->disableCSRFProtection) {
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

        $loginUrl = $this->openIdConnectService->getFinalLoginUrl($authContext, $code);

        $this->logger->info('Redirecting to login URL', ['url' => (string)$loginUrl]);

        return new RedirectResponse(GeneralUtility::locationHeaderUrl((string)$loginUrl), 303);
    }
}
