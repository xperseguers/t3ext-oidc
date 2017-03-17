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
                session_start();
            }
            $_SESSION['oidc_state'] = $state;
            $_SESSION['oidc_login_url'] = $loginUrl;

            $wrap = $pObj->conf['oidc.'];
            $linkTag = $pObj->cObj->stdWrap($authorizationUrl, $wrap);

            $markerArray['###OPENID_CONNECT###'] = $linkTag;
        }

        return $pObj->cObj->substituteMarkerArrayCached($params['content'], $markerArray);
    }

}
