<?php

declare(strict_types=1);

namespace Causal\Oidc\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class FrontendSimulationV12 extends FrontendSimulationV11
{
    public function getTypoScriptSetup(ServerRequestInterface $originalRequest, TypoScriptFrontendController $tsfe): array
    {
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $tsfe->getPageArguments()->getPageId());
        $tsfe->rootLine = $rootlineUtility->get();
        $newRequest = $tsfe->getFromCache($originalRequest);
        $tsfe->releaseLocks();
        return $newRequest->getAttribute('frontend.typoscript')->getSetupArray();
    }
}
