<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\Service\AuthenticationContextService;
use Causal\Oidc\Service\OpenIdConnectService;
use InvalidArgumentException;
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
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * see https://github.com/thephpleague/oauth2-client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'GET' && $this->openIdConnectService->isAuthenticationRequest($request)) {
            try {
                $authContext = $this->openIdConnectService->generateAuthenticationContext($request);
                $response = $this->openIdConnectService->getAuthorizationRedirect($authContext);
                return $response;
            } catch (InvalidArgumentException|Throwable $e) {
                $this->logger->alert('OIDC authentication provider error', ['exception' => $e]);
                // config error or
                // whatever the provider did wrong (can be connection errors)
                return (new Response())->withStatus(500)->withHeader('x-reason', 'Authentication provider error');
            }
        }
        return $handler->handle($request);
    }
}
