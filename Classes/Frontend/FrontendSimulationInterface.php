<?php

declare(strict_types=1);

namespace Causal\Oidc\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

interface FrontendSimulationInterface
{
    public function getCObj(ServerRequestInterface $originalRequest): ContentObjectRenderer;

    public function cleanupTSFE(): void;
}
