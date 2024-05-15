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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

class FrontendLoginEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function modifyLoginFormView(ModifyLoginFormViewEvent $event): void
    {
        $authService = GeneralUtility::makeInstance(OpenIdConnectService::class);
        try {
            $uri = $authService->generateOpenidConnectUri();
        } catch (\InvalidArgumentException $e) {
            $uri = '#InvalidOIDCConfiguration';
        }
        $event->getView()->assign('openidConnectUri', $uri);
    }
}
