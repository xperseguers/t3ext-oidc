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

            $wrap = $pObj->conf['oidc.'];
            $linkTag = $pObj->cObj->stdWrap($authorizationUrl, $wrap);

            $markerArray['###OPENID_CONNECT###'] = $linkTag;
        }

        return $pObj->cObj->substituteMarkerArrayCached($params['content'], $markerArray);
    }

}
