<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Oidc\EventListener;

use Causal\Oidc\Service\OpenIdConnectService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

class FrontendLoginEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function modifyLoginFormView(ModifyLoginFormViewEvent $event): void
    {
        $request = $event->getRequest();
        $currentUrl = new Uri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $redirectUrl = new Uri($request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '');

        $uri = GeneralUtility::makeInstance(OpenIdConnectService::class)->getFrontendAuthenticationRequestUrl(
            $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage()),
            $currentUrl,
            $redirectUrl,
        );
        if ($uri) {
            $event->getView()->assign('openidConnectUri', (string)$uri);
        }
    }
}
