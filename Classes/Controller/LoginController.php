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

namespace Causal\Oidc\Controller;

use Causal\Oidc\Service\OpenIdConnectService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class LoginController
{
    /**
     * TypoScript configuration of this plugin
     */
    protected array $pluginConfiguration = [];

    /**
     * will automatically be injected, if this controller is called as a plugin
     */
    public ?ContentObjectRenderer $cObj = null;

    protected ServerRequest $request;

    public function __construct()
    {
        $this->request = $GLOBALS['TYPO3_REQUEST'];
    }

    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * Main entry point for the OIDC plugin.
     *
     * If the user is not logged in, redirect to the authorization server to start the oidc process
     *
     * If the user has just been logged in and just came back from the authorization server, redirect the user to the
     * final redirect URL.
     *
     * @param string $_ ignored
     * @param array|null $pluginConfiguration
     * @throws PropagateResponseException
     */
    public function login(string $_, ?array $pluginConfiguration): void
    {
        if (is_array($pluginConfiguration)) {
            $this->pluginConfiguration = $pluginConfiguration;
        }

        $context = GeneralUtility::makeInstance(Context::class);
        $loginType = $this->request->getParsedBody()['logintype'] ?? $this->request->getQueryParams()['logintype'] ?? '';
        if ($loginType === 'login' || $context->getAspect('frontend.user')->isLoggedIn()) {
            $redirectUrl = $this->determineRedirectUrl();
            $this->redirect($redirectUrl);
        }

        $authorizationUrl = $this->determineAuthorizationUrl($pluginConfiguration['authorizationUrlOptions.'] ?? []);
        $this->redirect($authorizationUrl);
    }

    protected function determineAuthorizationUrl(array $authorizationUrlOptions): string
    {
        $oidcService = GeneralUtility::makeInstance(OpenIdConnectService::class);
        $authContext = $oidcService->generateAuthenticationContext($this->request, $authorizationUrlOptions);

        // The redirect will be handled by this plugin
        $authContext->redirectUrl = '';

        return $authContext->getAuthorizationUrl();
    }

    protected function determineRedirectUrl()
    {
        $redirectUrl = $this->request->getParsedBody()['redirect_url'] ?? $this->request->getQueryParams()['redirect_url'] ?? '';
        if (!empty($redirectUrl)) {
            return $redirectUrl;
        }

        if (isset($this->pluginConfiguration['defaultRedirectPid'])) {
            $defaultRedirectPid = (int)$this->pluginConfiguration['defaultRedirectPid'];
            if ($defaultRedirectPid > 0) {
                return $this->cObj->typoLink_URL(['parameter' => $defaultRedirectPid]);
            }
        }

        return '/';
    }

    /**
     * @throws PropagateResponseException
     */
    protected function redirect(string $redirectUrl): void
    {
        throw new PropagateResponseException(new RedirectResponse($redirectUrl));
    }
}
