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

class OidcLinkViewHelper extends AbstractViewHelper
{
    /**
     * @return string Authentication Request URL
     */
    public function render(): string
    {
        $url = GeneralUtility::makeInstance(OpenIdConnectService::class)->getAuthenticationRequestUrl();
        return (string)$url;
    }
}
