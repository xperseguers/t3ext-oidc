<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Causal\Oidc\Service\AuthenticationService;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Store access token if necessary.
 * That way the token can be renewed and user can be updated in consecutive requests.
 *
 * We use the middleware as ugly workaround, as the session data is only kept on none anonymous logins where a previous session existed.
 * We therefore can not securely save the session data within the AuthenticationService as part of login.
 */
final class StoreAccessTokenMiddleware implements MiddlewareInterface
{
    private ?AccessToken $accessToken = null;

    public function setAccessToken(AccessToken $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->accessToken instanceof AccessToken) {
            $request->getAttribute('frontend.user')?->setAndSaveSessionData(AuthenticationService::ACCESS_TOKEN_SESSION_KEY, $this->accessToken->jsonSerialize());
        }

        return $handler->handle($request);
    }
}
