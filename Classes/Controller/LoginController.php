<?php
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

use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class LoginController
{
    /**
     * Global oidc settings
     *
     * @var array
     */
    protected $settings;

    /**
     * TypoScript configuration of this plugin
     *
     * @var array
     */
    protected $pluginConfiguration;

    /**
     * @var ContentObjectRenderer will automatically be injected, if this controller is called as a plugin
     */
    public $cObj;

    public function __construct()
    {
        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;
        if (version_compare($typo3Branch, '9.0', '<')) {
            $this->settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc']);
        } else {
            $this->settings = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oidc'] ?? [];
        }
    }

    public function setContentObjectRenderer(ContentObjectRenderer $cObj)
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
     */
    public function login($_ = '', $pluginConfiguration)
    {
        if (is_array($pluginConfiguration)) {
            $this->pluginConfiguration = $pluginConfiguration;
        }

        if (GeneralUtility::_GP('logintype') == 'login') {
            // performRedirectAfterLogin stops flow by emitting a redirect
            $this->performRedirectAfterLogin();
        }
        $this->performRedirectToLogin($pluginConfiguration['authorizationUrlOptions.']);
    }

    protected function performRedirectToLogin(array $authorizationUrlOptions = [])
    {
        /** @var \Causal\Oidc\Service\OAuthService $service */
        $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class);
        $service->setSettings($this->settings);

        if (session_id() === '') {
            session_start();
        }
        $options = [];
        if ($this->settings['enableCodeVerifier']) {
            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
            $options = $this->addCodeChallengeToOptions($codeChallenge, $authorizationUrlOptions);
            $_SESSION['oidc_code_verifier'] = $codeVerifier;
        }
        $authorizationUrl = $service->getAuthorizationUrl($options);

        $state = $service->getState();
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_login_url'] = GeneralUtility::getIndpEnv('REQUEST_URI');
        $_SESSION['oidc_authorization_url'] = $authorizationUrl;
        unset($_SESSION['oidc_redirect_url']); // The redirect will be handled by this plugin

        $this->redirect($authorizationUrl);
    }

    protected function performRedirectAfterLogin()
    {
        $redirectUrl = $this->determineRedirectUrl();
        $this->redirect($redirectUrl);
    }

    protected function determineRedirectUrl()
    {
        if (!empty(GeneralUtility::_GP('redirect_url'))) {
            return GeneralUtility::_GP('redirect_url');
        }

        if (isset($this->pluginConfiguration['defaultRedirectPid'])) {
            $defaultRedirectPid = $this->pluginConfiguration['defaultRedirectPid'];
            if ((int)$defaultRedirectPid > 0) {
                return $this->cObj->typoLink_URL(['parameter' => $defaultRedirectPid]);
            }
        }

        return '/';
    }

    protected function redirect(string $redirectUrl): void
    {
        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;

        if (version_compare($typo3Branch, '11.0', '<')) {
            HttpUtility::redirect($redirectUrl);
        }

        throw new PropagateResponseException(new RedirectResponse($redirectUrl));
    }

    protected function generateCodeVerifier(): string
    {
        return bin2hex(random_bytes(64));
    }

    protected function convertVerifierToChallenge($codeVerifier)
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    protected function addCodeChallengeToOptions($codeChallenge, array $options = []): array
    {
        return array_merge(
            $options,
            [
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ]
        );
    }

}
