<?php

declare(strict_types=1);

namespace Causal\Oidc\Event;

use Psr\Http\Message\ServerRequestInterface;

class GetAuthorizationUrlEvent
{
    public function __construct(
        public readonly ?ServerRequestInterface $request,
        public readonly array $settings,
        public array $options,
    ) {}
}
