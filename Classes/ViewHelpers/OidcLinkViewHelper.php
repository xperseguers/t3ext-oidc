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

namespace Causal\Oidc\ViewHelpers;

use Causal\Oidc\Service\OpenIdConnectService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Core\Http\Uri;

class OidcLinkViewHelper extends AbstractViewHelper
{
    /**
     * @return string Authentication Request URL
     */
    public function render(): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $currentUrl = new Uri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $redirectUrl = new Uri($request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '');
        return (string)GeneralUtility::makeInstance(OpenIdConnectService::class)->getFrontendAuthenticationRequestUrl(
            $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage()),
            $currentUrl,
            $redirectUrl,
        );
    }
}
