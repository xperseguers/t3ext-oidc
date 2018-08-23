<?php


namespace Causal\Oidc\Controller;


use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class LoginController
{
    /**
     * Global oidc settings
     *
     * @var array
     */
    protected $settings;

    /**
     * TypoScript configuratoin of this plugin
     *
     * @var array
     */
    protected $pluginConfiguration;

    /**
     * @var ContentObjectRenderer
     *
     * Will be injected automatically, if this controller is called as a plugin
     */
    public $cObj;


    public function __construct()
    {
        $this->settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc']);
    }

    /**
     * @param string $_ ignored
     * @param array|null $pluginConfiguration
     *
     * Main entry point for the OIDC plugin.
     *
     * If the user is not logged in, redirect to the authorization server to start the oidc process
     *
     * If the user has just been logged in and just came back from the authorization server, redirect the user to the
     * final redirect URL.
     */
    public function login($_ = '', ?array $pluginConfiguration)
    {
        if (!empty($pluginConfiguration)) {
            $this->pluginConfiguration = $pluginConfiguration;
        }

        if (GeneralUtility::_GP('logintype') == 'login') {
            $this->performRedirectAfterLogin();
            // performRedirectAfterLogin stops flow by emitting a redirect
        }

        $this->performRedirectToLogin();
    }

    protected function performRedirectToLogin()
    {
        /** @var \Causal\Oidc\Service\OAuthService $service */
        $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class);
        $service->setSettings($this->settings);

        $authorizationUrl = $service->getAuthorizationUrl();

        if (session_id() === '') {
            session_start();
        }

        $state = $service->getState();
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_login_url'] = $_SERVER['REQUEST_URI'];
        $_SESSION['oidc_authorization_url'] = $authorizationUrl;
        unset($_SESSION['oidc_redirect_url']); // The redirect will be handled by this plugin

        HttpUtility::redirect($authorizationUrl);
    }

    protected function performRedirectAfterLogin()
    {
        $redirectUrl = $this->determineRedirectUrl();
        HttpUtility::redirect($redirectUrl);
    }

    protected function determineRedirectUrl()
    {
        if (! empty(GeneralUtility::_GP('redirect_url'))) {
            return GeneralUtility::_GP('redirect_url');
        }

        if (isset($this->pluginConfiguration['defaultRedirectPid'])) {
            $defaultRedirectPid = $this->pluginConfiguration['defaultRedirectPid'];
            if ((int) $defaultRedirectPid > 0) {
                return $this->cObj->typoLink_URL(['parameter' => $defaultRedirectPid]);
            }
        }

        return '/';
    }
}
