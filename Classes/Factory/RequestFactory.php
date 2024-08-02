<?php

declare(strict_types=1);

namespace Causal\Oidc\Factory;

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
        $request = $this->requestFactory->createRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader((string)$name, $value);
        }
        if ($body !== '' && $body !== null) {
            $request = $request->withBody(Utils::streamFor($body));
        }
        return $request->withProtocolVersion($version);
    }
}
