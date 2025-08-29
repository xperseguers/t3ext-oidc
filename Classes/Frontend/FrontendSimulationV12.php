<?php

declare(strict_types=1);

namespace Causal\Oidc\Frontend;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class FrontendSimulationV12 implements FrontendSimulationInterface
{
    public function getTSFE(ServerRequestInterface $originalRequest): TypoScriptFrontendController
    {
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $routeResult = $siteMatcher->matchRequest($originalRequest);
        if ($routeResult instanceof SiteRouteResult) {
            $site = $routeResult->getSite();
            if ($site instanceof Site) {
                try {
                    if ($routeResult->getTail() === 'typo3/login') {
                        $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $site->getRootPageId(), '0', []);
                    } else {
                        $pageArguments = $site->getRouter()->matchRequest($originalRequest, $routeResult);
                    }

                    if ($pageArguments instanceof PageArguments) {
                        return GeneralUtility::makeInstance(
                            TypoScriptFrontendController::class,
                            GeneralUtility::makeInstance(Context::class),
                            $site,
                            $routeResult->getLanguage(),
                            $pageArguments,
                            GeneralUtility::makeInstance(FrontendUserAuthentication::class)
                        );
                    }
                } catch (RouteNotFoundException) {
                }
            }
        }
        throw new InvalidArgumentException('Failed to initialize TSFE');
    }

    public function getTypoScriptSetup(ServerRequestInterface $originalRequest, TypoScriptFrontendController $tsfe): array
    {
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $tsfe->getPageArguments()->getPageId());
        $tsfe->rootLine = $rootlineUtility->get();
        $newRequest = $tsfe->getFromCache($originalRequest);
        $tsfe->releaseLocks();
        return $newRequest->getAttribute('frontend.typoscript')->getSetupArray();
    }

    public function cleanupTSFE(): void
    {
        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $context->unsetAspect('typoscript');
        $context->unsetAspect('frontend.preview');
        unset($GLOBALS['TSFE']);
    }
}
