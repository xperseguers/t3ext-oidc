<?php

declare(strict_types=1);

namespace Causal\Oidc\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

interface FrontendSimulationInterface
{
    public function getTSFE(ServerRequestInterface $originalRequest): TypoScriptFrontendController;

    public function getTypoScriptSetup(ServerRequestInterface $originalRequest, TypoScriptFrontendController $tsfe): array;

    public function cleanupTSFE(): void;
}
