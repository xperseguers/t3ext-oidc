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
        static::getLogger()->debug('Post-processing markers for felogin form');
        $markerArray['###OPENID_CONNECT###'] = '';

        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc']);

        if (empty($settings['oidcClientKey'])
            || empty($settings['oidcClientSecret'])
            || empty($settings['oidcEndpointAuthorize'])
            || empty($settings['oidcEndpointToken'])
        ) {
            $markerArray['###OPENID_CONNECT###'] = 'Invalid OpenID Connect configuration';
        } else {
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

            if (session_id() === '') { // If no session exists, start a new one
                static::getLogger()->debug('No PHP session found');
                session_start();
            }
            $_SESSION['oidc_state'] = $state;
            $_SESSION['oidc_login_url'] = $loginUrl;

            static::getLogger()->debug('PHP session is available', [
                'id' => session_id(),
                'data' => $_SESSION,
            ]);

            $wrap = $pObj->conf['oidc.'];
            $linkTag = $pObj->cObj->stdWrap($authorizationUrl, $wrap);

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
