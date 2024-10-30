<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\Service\OpenIdConnectService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;

class AuthenticationUrlRequest implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'GET' && $this->openIdConnectService->isAuthenticationRequest($request)) {
            try {
                $authContext = $this->openIdConnectService->generateAuthenticationContext($request);
                $uri = $authContext->getAuthorizationUrl();
                return new RedirectResponse($uri);
            } catch (InvalidArgumentException|Throwable) {
                // config error or
                // whatever the provider did wrong (can be connection errors)
                return (new Response())->withStatus(500, 'Authentication provider error');
            }
        }
        return $handler->handle($request);
    }
}
