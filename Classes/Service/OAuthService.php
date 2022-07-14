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
     * @param array $options
     * @return string
     */
    public function getAuthorizationUrl(array $options = [])
    {
        if (!empty($this->settings['oidcAuthorizeLanguageParameter'])) {
            $languageOption = $this->settings['oidcAuthorizeLanguageParameter'];

            if (isset($GLOBALS['TSFE']->lang)) {
                $frontendLanguage = $GLOBALS['TSFE']->lang;
                $options[$languageOption] = $frontendLanguage;
            }
        }

        $authorizationUrl = $this->getProvider()->getAuthorizationUrl($options);

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
     * Returns an AccessToken using either authorization code grant or resource owner password
     * credentials grant.
     *
     * @param string $codeOrUsername Either a code or the username (if password is provided)
     * @param null $password Optional parameter if authenticating with authorization code grant
     * @param null $codeVerifier Code verifier for PKCE
     * @return AccessToken
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function getAccessToken($codeOrUsername, $password = null, $codeVerifier = null)
    {
        if ($password === null) {
            $options = ['code' => $codeOrUsername];
            if ($codeVerifier !== null) {
                $options['code_verifier'] = $codeVerifier;
            }
            $accessToken = $this->getProvider()->getAccessToken('authorization_code', $options);
        } else {
            $accessToken = $this->getProvider()->getAccessToken('password', [
                'username' => $codeOrUsername,
                'password' => $password,
                // Oddly, the client does not send scope along automatically but WSO2 expects it anyway...
                'scope' => implode(',', $this->getProvider()->getDefaultScopes()),
            ]);
        }

        return $accessToken;
    }

    /**
     * Returns an AccessToken using request path authentication.
     *
     * This non-standard behaviour is described on
     * https://docs.wso2.com/display/IS530/Try+Password+Grant
     *
     * @param string $username
     * @param string $password
     * @return AccessToken
     */
    public function getAccessTokenWithRequestPathAuthentication($username, $password)
    {
        $redirectUri = $this->settings['oidcRedirectUri'];
        if (empty($redirectUri)) {
            $redirectUri = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://';
            $redirectUri .= GeneralUtility::getIndpEnv('HTTP_HOST');
            $redirectUri .= '/typo3conf/ext/oidc/Resources/Public/callback.php';
        }
        $url = $this->settings['oidcEndpointAuthorize'] . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $this->settings['oidcClientKey'],
                'scope' => $this->settings['oidcClientScopes'],
                'redirect_uri' => $redirectUri,
            ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($username . ':' . $password),
        ]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $content = curl_exec($ch);

        if ($content === false) {
            throw new \RuntimeException('Curl ERROR: ' . curl_error($ch), 1510049345);
        }
        curl_close($ch);

        $headers = explode(LF, $content);
        foreach ($headers as $header) {
            list($key, $value) = GeneralUtility::trimExplode(':', $header, false, 2);
            if ($key === 'Location') {
                $queryParams = explode('&', substr($value, strpos($value, '?') + 1));
                foreach ($queryParams as $param) {
                    list($key, $value) = explode('=', $param, 2);
                    if ($key === 'code') {
                        return $this->getAccessToken($value);
                    }
                }
            }
        }

        return null;
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
     * Revokes the access token.
     *
     * @param AccessToken $token
     * @return bool
     */
    public function revokeToken(AccessToken $token)
    {
        if (empty($this->settings['oidcEndpointRevoke'])) {
            return false;
        }

        $provider = $this->getProvider();
        $request = $provider->getRequest(
            \League\OAuth2\Client\Provider\AbstractProvider::METHOD_POST,
            $this->settings['oidcEndpointRevoke'],
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->settings['oidcClientKey'] . ':' . $this->settings['oidcClientSecret']),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => 'token=' . $token->getToken(),
            ]
        );
        $response = $provider->getParsedResponse($request);

        return true;
    }

    /**
     * Returns the OAuth client provider.
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
    protected function getProvider()
    {
        if ($this->provider === null) {
            $redirectUri = $this->settings['oidcRedirectUri'];
            if (empty($redirectUri)) {
                $redirectUri = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://';
                $redirectUri .= GeneralUtility::getIndpEnv('HTTP_HOST');
                $redirectUri .= '/typo3conf/ext/oidc/Resources/Public/callback.php';
            }

            $this->provider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId' => $this->settings['oidcClientKey'],
                'clientSecret' => $this->settings['oidcClientSecret'],
                'redirectUri' => $redirectUri,
                'urlAuthorize' => $this->settings['oidcEndpointAuthorize'],
                'urlAccessToken' => $this->settings['oidcEndpointToken'],
                'urlResourceOwnerDetails' => $this->settings['oidcEndpointUserInfo'],
                'scopes' => GeneralUtility::trimExplode(',', $this->settings['oidcClientScopes'], true),
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
