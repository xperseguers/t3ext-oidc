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
     * @return array|bool
     * @throws \RuntimeException
     */
    public function getUser()
    {
        $params = GeneralUtility::_GET('tx_oidc');
        if (empty($params['code'])) {
            return false;
        }

        static::getLogger()->debug('Initializing OpenID Connect service');

        /** @var \Causal\Oidc\Service\OAuthService $service */
        $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class);
        $service->setSettings($this->config);

        // Try to get an access token using the authorization code grant
        try {
            static::getLogger()->debug('Retrieving an access token');
            $accessToken = $service->getAccessToken($params['code']);
            static::getLogger()->debug('Access token retrieved', $accessToken->jsonSerialize());
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Probably a "server_error", meaning the code is not valid anymore
            static::getLogger()->error('Code has been refused by the authentication server', [
                'code' => $params['code'],
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('The code has been refused by the authentication server. Maybe it was used twice.', 1489743507);
        }

        // Using the access token, we may look up details about the resource owner
        $resourceOwner = $service->getResourceOwner($accessToken)->toArray();
        static::getLogger()->debug('Resource owner retrieved', $resourceOwner);
        if (empty($resourceOwner['contact_number'])) {
            static::getLogger()->error('No "contact_number" found in resource owner, revoking access token');
            $service->revokeToken($accessToken);
            throw new \RuntimeException('Resource owner does not have a contact number: ' . json_encode($resourceOwner) . '. Your access token has been revoked. Please try again.', 1490086626);
        }
        $user = $this->convertResourceOwner($resourceOwner);

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

        if ($row && (bool)$row['disable']) {
            // User was manually disabled, it should not get automatically re-enabled
            static::getLogger()->info('User was manually disabled, denying access', ['user' => $row]);

            return false;
        }

        /** @var $objInstanceSaltedPW \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface */
        $objInstanceSaltedPW = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(null, TYPO3_MODE);
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 20);
        $hashedPassword = $objInstanceSaltedPW->getHashedPassword($password);

        $data = [
            'username' => 'contact_' . $info['contact_number'],
            'password' => $hashedPassword,
            'name' => $info['name'],
            'first_name' => $info['given_name'],
            'last_name' => $info['family_name'],
            'address' => $info['street_address'],
            'title' => $info['title'],
            'zip' => $info['postal_code'],
            'city' => $info['locality'],
            'country' => $info['country'],
        ];

        $newUsergroups = [];
        $defaultUserGroups = GeneralUtility::intExplode(',', $this->config['usersDefaultGroup'], true);

        if ($row) {
            $currentUserGroups = GeneralUtility::intExplode(',', $row['usergroup'], true);
            if (!empty($currentUserGroups)) {
                $oidcUserGroups = $database->exec_SELECTgetRows(
                    'uid',
                    'fe_groups',
                    'uid IN (' . implode(',', $currentUserGroups) . ') AND tx_oidc_pattern<>\'\'',
                    '',
                    '',
                    '',
                    'uid'
                );
                // Remove OIDC-related groups
                $newUsergroups = array_diff($currentUserGroups, array_keys($oidcUserGroups));
            }
        }

        // Map OIDC roles to TYPO3 user groups
        if (!empty($info['Roles'])) {
            $typo3Roles = $database->exec_SELECTgetRows(
                'uid, tx_oidc_pattern',
                'fe_groups',
                'tx_oidc_pattern<>\'\' AND hidden=0 AND deleted=0'
            );
            $roles = GeneralUtility::trimExplode(',', $info['Roles'], true);
            $roles = ',' . implode(',', $roles) . ',';

            foreach ($typo3Roles as $typo3Role) {
                $pattern = $typo3Role['tx_oidc_pattern'];
                $pattern = str_replace(['?', '+', '.', '*'], ['[?]', '[+]', '[.]', '[^,]*'], $pattern);
                if (preg_match('/,' . $pattern . ',/', $roles)) {
                    $newUsergroups[] = (int)$typo3Role['uid'];
                }
            }
        }

        // Add default user groups
        $newUsergroups = array_unique(array_merge($newUsergroups, $defaultUserGroups));

        if ($row) { // fe_users record already exists => update it
            static::getLogger()->info('Detected a returning user');
            $data['usergroup'] = implode(',', $newUsergroups);
            $user = array_merge($row, $data);
            if ($user != $row) {
                static::getLogger()->debug('Updating existing user', [
                    'old' => $row,
                    'new' => $user,
                ]);
                $user['tstamp'] = $GLOBALS['EXEC_TIME'];
                $database->exec_UPDATEquery(
                    'fe_users',
                    'uid=' . $user['uid'],
                    $user
                );
            }
        } else {    // fe_users record does not already exist => create it
            static::getLogger()->info('New user detected, creating a TYPO3 user');
            $data = array_merge($data, [
                'pid' => $this->config['usersStoragePid'],
                'usergroup' => implode(',', $newUsergroups),
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

        static::getLogger()->debug('Authentication user record processed', $user);

        // We need that for the upcoming call to authUser()
        $user['tx_oidc'] = true;

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
