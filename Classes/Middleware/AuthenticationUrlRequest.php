<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\Service\AuthenticationContextService;
use Causal\Oidc\Service\OpenIdConnectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Http\Response;

class AuthenticationUrlRequest implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected OpenIdConnectService $openIdConnectService,
        protected AuthenticationContextService $authenticationContextService,
    ) {}

    /**
     * see https://github.com/thephpleague/oauth2-client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'GET' && $this->openIdConnectService->isAuthenticationRequest($request)) {
            try {
                $authContext = $this->openIdConnectService->generateAuthenticationContext($request);
                return $this->openIdConnectService->getAuthorizationRedirect($authContext);
            } catch (Throwable $e) {
                $this->logger->alert('OIDC authentication provider error', ['exception' => $e]);
                // config error or
                // whatever the provider did wrong (can be connection errors)
                return new Response()->withStatus(500)->withHeader('x-reason', 'Authentication provider error');
            }
        }
        return $handler->handle($request);
    }
}
