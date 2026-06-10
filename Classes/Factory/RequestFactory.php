<?php

declare(strict_types=1);

namespace Causal\Oidc\Factory;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use League\OAuth2\Client\Tool\RequestFactory as Oauth2RequestFactory;
use Psr\Http\Message\RequestFactoryInterface;

class RequestFactory extends Oauth2RequestFactory
{
    protected RequestFactoryInterface $requestFactory;

    public function __construct(RequestFactoryInterface $requestFactory)
    {
        $this->requestFactory = $requestFactory;
    }

    public function getRequest(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $version = '1.1'
    ) {
        // Use Guzzle's PSR-7 request — Guzzle middlewares (PrepareBodyMiddleware
        // in particular) set Content-Length as int. TYPO3's PSR-7 Message is
        // strict-string and throws InvalidArgumentException #1436717266 on
        // non-string header values, while Guzzle's own Request accepts them.
        // The Causal\Oidc HTTP flow is purely server-to-server through the
        // Guzzle client, so a Guzzle Request is the correct PSR-7 implementation
        // here regardless of the injected RequestFactoryInterface.
        $request = new GuzzleRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader((string)$name, $value);
        }
        if ($body !== '' && $body !== null) {
            $request = $request->withBody(Utils::streamFor($body));
        }
        return $request->withProtocolVersion($version);
    }
}
