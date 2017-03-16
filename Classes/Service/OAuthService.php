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

namespace Causal\Oidc\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use League\OAuth2\Client\Token\AccessToken;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Class OAuthService.
 */
class OAuthService
{

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var \League\OAuth2\Client\Provider\GenericProvider
     */
    protected $provider;

    /**
     * Sets the settings.
     *
     * @param array $settings
     * @return $this
     */
    public function setSettings(array $settings)
    {
        $this->settings = $settings;
        return $this;
    }

    /**
     * Returns the authorization URL.
     *
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $authorizationUrl = $this->getProvider()->getAuthorizationUrl();
        return $authorizationUrl;
    }

    /**
     * Returns the state generated for us.
     *
     * @return string
     * @see getAuthorizationUrl()
     */
    public function getState()
    {
        return $this->getProvider()->getState();
    }

    /**
     * Returns an AccessToken.
     *
     * @param string $code
     * @return AccessToken
     */
    public function getAccessToken($code)
    {
        return $this->getProvider()->getAccessToken('authorization_code', [
            'code' => $code
        ]);
    }

    /**
     * Returns the resource owner.
     *
     * @param AccessToken $token
     * @return \League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    public function getResourceOwner(AccessToken $token)
    {
        return $this->getProvider()->getResourceOwner($token);
    }

    /**
     * Returns the OAuth client provider.
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
    protected function getProvider()
    {
        if ($this->provider === null) {
            $redirectUri = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/typo3conf/ext/oidc/callback.php';

            $this->provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId' => $this->settings['oidcClientKey'],
                'clientSecret' => $this->settings['oidcClientSecret'],
                'redirectUri' => $redirectUri,
                'urlAuthorize' => $this->settings['oidcEndpointAuthorize'],
                'urlAccessToken' => $this->settings['oidcEndpointToken'],
                'urlResourceOwnerDetails' => $this->settings['oidcEndpointUserInfo'],
                'scopes' => ['openid'],
            ]);
        }
        return $this->provider;
    }

    public function getFreshAccessToken()
    {
        $serializedToken = $this->settings['access_token'];
        $options = json_decode($serializedToken, true);
        if (empty($serializedToken) || empty($options)) {
            // Invalid token
            return null;
        }
        $accessToken = new \League\OAuth2\Client\Token\AccessToken($options);

        if ($accessToken->hasExpired()) {
            try {
                $newAccessToken = $this->getProvider()->getAccessToken('refresh_token', [
                    'refresh_token' => $accessToken->getRefreshToken(),
                ]);

                // TODO
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                // TODO: log problem
                return null;
            }
        }

        return $accessToken;
    }

}
