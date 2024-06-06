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
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

class FrontendLoginEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function modifyLoginFormView(ModifyLoginFormViewEvent $event): void
    {
        $authService = GeneralUtility::makeInstance(OpenIdConnectService::class);
        try {
            $authContext = $authService->generateAuthenticationContext($GLOBALS['TYPO3_REQUEST']);
            $uri = $authContext->getAuthorizationUrl();
        } catch (InvalidArgumentException $e) {
            $uri = '#InvalidOIDCConfiguration';
        } catch (Throwable $e) {
            // whatever the provider did wrong (can be connection errors)
            $uri = '#oidcError';
        }
        $event->getView()->assign('openidConnectUri', $uri);
    }
}
