<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;

class OauthCallback implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * see https://github.com/thephpleague/oauth2-client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? '';
        if ($code) {
            $state = $queryParams['state'] ?? '';
            if (!$state) {
                return (new Response())->withStatus(400, 'Invalid state');
            }

            $queryParams['type'] = 1489657462;
            $request = $request->withQueryParams($queryParams);
        }
        return $handler->handle($request);
    }
}
