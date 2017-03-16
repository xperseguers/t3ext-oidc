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

    /**
     * @internal
     */
    public function callback()
    {
        if ((empty($_GET['state']) || empty($_GET['code']))) {
            throw new \RuntimeException('No state or code detected', 1487001047);
        }
        $rows = BackendUtility::getRecordsByField(
            'tx_oidc_application',
            'state',
            $_GET['state']
        );
        if (count($rows) !== 1) {
            throw new \RuntimeException('Invalid state provided', 1487001084);
        }

        $this->setApplication($rows[0]);
        $provider = $this->getProvider();

        try {

            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            // We have an access token, which we may use in authenticated
            // requests against the service provider's API
            static::getDatabaseConnection()->exec_UPDATEquery(
                'tx_oidc_application',
                'uid=' . $this->application['uid'],
                [
                    'state' => '',
                    'access_token' => json_encode($accessToken),
                ]
            );

            echo <<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<body>

<h1>Please close this browser window and go back to the Reports module to check the connection.</h1>

</body>
</html>

HTML;

            die();

            /*
            echo $accessToken->getToken() . LF;
            echo $accessToken->getRefreshToken() . LF;
            echo $accessToken->getExpires() . LF;
            echo ($accessToken->hasExpired() ? 'expired' : 'not expired') . LF;

            // Using the access token, we may look up details about the
            // resource owner.
            $resourceOwner = $provider->getResourceOwner($accessToken);

            var_export($resourceOwner->toArray());

            // The provider provides a way to get an authenticated API request for
            // the service, using the access token; it returns an object conforming
            // to Psr\Http\Message\RequestInterface.
            $request = $provider->getAuthenticatedRequest(
                'GET',
                'http://brentertainment.com/oauth2/lockdin/resource',
                $accessToken
            );
            */

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

            // Failed to get the access token or user details.
            exit($e->getMessage());
        }
    }

    /**
     * Returns the database connection.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected static function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
