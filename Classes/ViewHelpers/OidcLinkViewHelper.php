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
use InvalidArgumentException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class OidcLinkViewHelper extends AbstractViewHelper
{

    use CompileWithRenderStatic;

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string URI
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
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
        return $uri;
    }
}
