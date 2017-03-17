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
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * OpenID Connect authentication service.
 */
class AuthenticationService extends \TYPO3\CMS\Sv\AuthenticationService
{

    /**
     * true - this service was able to authenticate the user
     */
    const STATUS_AUTHENTICATION_SUCCESS_CONTINUE = true;

    /**
     * 200 - authenticated and no more checking needed
     */
    const STATUS_AUTHENTICATION_SUCCESS_BREAK = 200;

    /**
     * false - this service was the right one to authenticate the user but it failed
     */
    const STATUS_AUTHENTICATION_FAILURE_BREAK = false;

    /**
     * 100 - just go on. User is not authenticated but there's still no reason to stop
     */
    const STATUS_AUTHENTICATION_FAILURE_CONTINUE = 100;

    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc'];
        $this->config = $config ? unserialize($config) : [];
    }

    /**
     * Finds a user.
     *
     * @return int|bool|array
     */
    public function getUser()
    {
        $params = GeneralUtility::_GET('tx_oidc');
        if (empty($params['code'])) {
            return false;
        }

        /** @var \Causal\Oidc\Service\OAuthService $service */
        $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class);
        $service->setSettings($this->config);

        // Try to get an access token using the authorization code grant
        try {
            $accessToken = $service->getAccessToken($params['code']);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Probably a "server_error", meaning the code is not valid anymore
            throw new \RuntimeException('The code has been refused by the authentication server. Maybe it was used twice.', 1489743507);
        }

        // Using the access token, we may look up details about the resource owner
        $resourceOwner = $service->getResourceOwner($accessToken)->toArray();
        $user = $this->convertResourceOwner($resourceOwner);

        $user['tx_oidc'] = true;

        return $user;
    }

    /**
     * Authenticate a user
     *
     * @oaram array $user
     * @return int
     */
    public function authUser(array $user)
    {
        $status = static::STATUS_AUTHENTICATION_FAILURE_CONTINUE;

        if (!empty($user['tx_oidc'])) {
            $status = static::STATUS_AUTHENTICATION_SUCCESS_BREAK;
        }

        return $status;
    }

    /**
     * Converts a resource owner into a TYPO3 Frontend user.
     *
     * @param array $info
     * @return array
     */
    protected function convertResourceOwner(array $info)
    {
        $user = [];
        $database = $this->getDatabaseConnection();
        $row = $database->exec_SELECTgetSingleRow(
            '*',
            'fe_users',
            'tx_oidc=' . (int)$info['contact_number']
        );

        /** @var $objInstanceSaltedPW \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface */
        $objInstanceSaltedPW = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(null, TYPO3_MODE);
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 20);
        $hashedPassword = $objInstanceSaltedPW->getHashedPassword($password);

        $data = [
            'username' => 'contact_' . $info['contact_number'],
            'password' => $hashedPassword,
            'disable' => 0,
            'name' => $info['name'],
            'first_name' => $info['given_name'],
            'last_name' => $info['family_name'],
            'address' => $info['street_address'],
            'title' => $info['title'],
            'zip' => $info['postal_code'],
            'city' => $info['locality'],
            'country' => $info['country'],
        ];

        if ($row) { // fe_users record already exists => update it
            $user = array_merge($row, $data);
            if ($user != $row) {
                $user['tstamp'] = $GLOBALS['EXEC_TIME'];
                $database->exec_UPDATEquery(
                    'fe_users',
                    'uid=' . $user['uid'],
                    $user
                );
            }
        } else {    // fe_users record does not already exist => create it
            $data = array_merge($data, [
                'pid' => $this->config['usersStoragePid'],
                'usergroup' => $this->config['usersDefaultGroup'],
                'crdate' => $GLOBALS['EXEC_TIME'],
                'tx_oidc' => (int)$info['contact_number'],
            ]);
            $database->exec_INSERTquery(
                'fe_users',
                $data
            );
            // Retrieve the created user from database to get all columns
            $user = $database->exec_SELECTgetSingleRow(
                '*',
                'fe_users',
                'uid=' . $database->sql_insert_id()
            );
        }

        return $user;
    }

    /**
     * Returns the database connection
     * This method only exists in TYPO3 v7 in parent class.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
