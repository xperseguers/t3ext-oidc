<?php

declare(strict_types=1);

namespace Causal\Oidc\Frontend;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Aspect\PreviewAspect;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageInformationFactory;

class FrontendSimulationV13 extends FrontendSimulationV12
{
    public function getTSFE(ServerRequestInterface $originalRequest): TypoScriptFrontendController
    {
        return GeneralUtility::makeInstance(TypoScriptFrontendController::class);
    }

    public function getTypoScriptSetup(ServerRequestInterface $originalRequest, TypoScriptFrontendController $tsfe): array
    {
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $routeResult = $siteMatcher->matchRequest($originalRequest);
        if ($routeResult instanceof SiteRouteResult) {
            $site = $routeResult->getSite();
            if ($site instanceof Site) {
                try {
                    /** @var Context $context */
                    $context = GeneralUtility::makeInstance(Context::class);
                    $context->setAspect('frontend.preview', new PreviewAspect());

                    $cacheInstruction = $originalRequest->getAttribute('frontend.cache.instruction', new CacheInstruction());
                    $originalRequest = $originalRequest->withAttribute('frontend.cache.instruction', $cacheInstruction);

                    $pageArguments = $site->getRouter()->matchRequest($originalRequest, $routeResult);
                    $originalRequest = $originalRequest->withAttribute('routing', $pageArguments);

                    $pageInformationFactory = GeneralUtility::makeInstance(PageInformationFactory::class);
                    $pageInformation = $pageInformationFactory->create($originalRequest);
                    $originalRequest = $originalRequest->withAttribute('frontend.page.information', $pageInformation);

                    $expressionMatcherVariables = $this->getExpressionMatcherVariables($site, $originalRequest, $tsfe);
                    /** @var CacheManager $cacheManager */
                    $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
                    /** @var PhpFrontend $cache */
                    $cache = $cacheManager->getCache('typoscript');

                    $frontendTypoScriptFactory = GeneralUtility::makeInstance(FrontendTypoScriptFactory::class);
                    $frontendTypoScript = $frontendTypoScriptFactory->createSettingsAndSetupConditions(
                        $site,
                        $pageInformation->getSysTemplateRows(),
                        // $originalRequest does not contain site ...
                        $expressionMatcherVariables,
                        $cache,
                    );
                    $frontendTypoScript = $frontendTypoScriptFactory->createSetupConfigOrFullSetup(
                        false,
                        $frontendTypoScript,
                        $site,
                        $pageInformation->getSysTemplateRows(),
                        $expressionMatcherVariables,
                        '0',
                        $cache,
                        null
                    );

                    return $frontendTypoScript->getSetupArray();
                } catch (RouteNotFoundException) {
                }
            }
        }
        throw new InvalidArgumentException('Failed to build TypoScript');
    }

    protected function getExpressionMatcherVariables(
        SiteInterface $site,
        ServerRequestInterface $request,
        TypoScriptFrontendController $controller
    ): array {
        $pageInformation = $request->getAttribute('frontend.page.information');
        $topDownRootLine = $pageInformation->getRootLine();
        $localRootline = $pageInformation->getLocalRootLine();
        ksort($topDownRootLine);
        return [
            'request' => $request,
            'pageId' => $pageInformation->getId(),
            'page' => $pageInformation->getPageRecord(),
            'fullRootLine' => $topDownRootLine,
            'localRootLine' => $localRootline,
            'site' => $site,
            'siteLanguage' => $request->getAttribute('language'),
            'tsfe' => $controller,
        ];
    }
}
