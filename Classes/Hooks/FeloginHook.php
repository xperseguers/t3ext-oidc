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

namespace Causal\Oidc\Hooks;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks into EXT:felogin to support custom markers.
 */
class FeloginHook
{

    /**
     * @param array $params
     * @param \TYPO3\CMS\Felogin\Controller\FrontendLoginController $pObj
     */
    public function postProcContent(array $params, \TYPO3\CMS\Felogin\Controller\FrontendLoginController $pObj)
    {
        $requestId = $this->getUniqueId();
        static::getLogger()->debug('Post-processing markers for felogin form', ['request' => $requestId]);
        $markerArray['###OPENID_CONNECT###'] = '';

        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc');

        if (empty($settings['oidcClientKey'])
            || empty($settings['oidcClientSecret'])
            || empty($settings['oidcEndpointAuthorize'])
            || empty($settings['oidcEndpointToken'])
        ) {
            $markerArray['###OPENID_CONNECT###'] = 'Invalid OpenID Connect configuration';
        } else {
            if (session_id() === '') { // If no session exists, start a new one
                static::getLogger()->debug('No PHP session found');
                session_start();
            }

            if (empty($_SESSION['requestId']) || $_SESSION['requestId'] !== $requestId) {
                $this->prepareAuthorizationUrl($settings);
                $_SESSION['requestId'] = $requestId;
                $_SESSION['oidc_redirect_url'] = GeneralUtility::_GP('redirect_url');

                static::getLogger()->debug('PHP session is available', [
                    'id' => session_id(),
                    'data' => $_SESSION,
                ]);
            } else {
                static::getLogger()->debug('Reusing same authorization URL and state');
            }

            $wrap = $pObj->conf['oidc.'];
            $linkTag = $pObj->cObj->stdWrap($_SESSION['oidc_authorization_url'], $wrap);

            $markerArray['###OPENID_CONNECT###'] = $linkTag;
        }

        if (version_compare(TYPO3_branch, '8', '>=')) {
            /** @var \TYPO3\CMS\Core\Service\MarkerBasedTemplateService $templateService */
            $templateService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\MarkerBasedTemplateService::class);
            $content = $templateService->substituteMarkerArrayCached($params['content'], $markerArray);
        } else {
            $content = $pObj->cObj->substituteMarkerArrayCached($params['content'], $markerArray);
        }

        return $content;
    }

    /**
     * Prepares the authorization URL and corresponding expected state (to mitigate CSRF attack)
     * and stores information into the session.
     *
     * @param array $settings
     * @return void
     */
    protected function prepareAuthorizationUrl(array $settings)
    {
        /** @var \Causal\Oidc\Service\OAuthService $service */
        $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class);
        $service->setSettings($settings);
        $authorizationUrl = $service->getAuthorizationUrl();

        // Store the state
        $state = $service->getState();

        static::getLogger()->debug('Generating authorization URL', [
            'url' => $authorizationUrl,
            'state' => $state,
        ]);

        $loginUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        // Sanitize the URL
        $parts = parse_url($loginUrl);
        $queryParts = array_filter(explode('&', $parts['query']), function ($v) {
            list ($k,) = explode('=', $v, 2);

            return !in_array($k, ['logintype', 'tx_oidc[code]']);
        });
        $parts['query'] = implode('&', $queryParts);
        $loginUrl = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
        if (!empty($parts['query'])) {
            $loginUrl .= '?' . $parts['query'];
        }

        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_login_url'] = $loginUrl;
        $_SESSION['oidc_authorization_url'] = $authorizationUrl;
    }

    /**
     * Returns a unique ID for the current processed request.
     *
     * This is supposed to be independent of the actual web server (Nginx or Apache) and
     * the way PHP was built and unique enough for our use case, as opposed to using:
     *
     * - zend_thread_id() which requires PHP to be built with Zend Thread Safety - ZTS - support and debug mode
     * - apache_getenv('UNIQUE_ID') which requires Apache as web server and mod_unique_id
     *
     * @return string
     */
    protected function getUniqueId()
    {
        $uniqueId = sprintf('%08x', abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME'] . $_SERVER['REMOTE_PORT'])));

        return $uniqueId;
    }

    /**
     * Returns a logger.
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected static function getLogger()
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        static $logger = null;
        if ($logger === null) {
            $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        }

        return $logger;
    }

}
