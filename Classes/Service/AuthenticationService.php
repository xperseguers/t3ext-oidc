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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use Causal\Oidc\Service\OAuthService;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
     * @var array
     */
    private $config;

    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        $extConf = isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc'])
            ? unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc'])
            : [];
        $this->config = $this->getConfig($extConf);
    }

    /**
     * Finds a user.
     *
     * @return array|bool
     * @throws \RuntimeException
     */
    public function getUser()
    {
        $user = false;
        $params = GeneralUtility::_GET('tx_oidc');
        $code = isset($params['code']) ? $params['code'] : null;
        $username = isset($this->login['uname']) ? $this->login['uname'] : null;

        if (isset($this->login['uident_text'])) {
            $password = $this->login['uident_text'];
        } elseif (isset($this->login['uident'])) {
            $password = $this->login['uident'];
        } else {
            $password = null;
        }

        if ($code !== null) {
            $user = $this->authenticateWithAuhorizationCode($code);
        } elseif (!(empty($username) || empty($password))) {
            $user = $this->authenticateWithResourceOwnerPasswordCredentials($username, $password);
        }

        return $user;
    }

    /**
     * Authenticates a user using authorization code grant.
     *
     * @param string $code
     * @return array|bool
     */
    protected function authenticateWithAuhorizationCode($code)
    {
        static::getLogger()->debug('Initializing OpenID Connect service');

        /** @var OAuthService $service */
        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($this->config);

        // Try to get an access token using the authorization code grant
        try {
            static::getLogger()->debug('Retrieving an access token');
            $accessToken = $service->getAccessToken($code);
            static::getLogger()->debug('Access token retrieved', $accessToken->jsonSerialize());
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Probably a "server_error", meaning the code is not valid anymore
            static::getLogger()->error('Possibly replay: code has been refused by the authentication server', [
                'code' => $code,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        $user = $this->getUserFromAccessToken($service, $accessToken);
        return $user;
    }

    /**
     * Authenticates a user using resource owner password credentials grant.
     *
     * @param string $username
     * @param string $password
     * @return array|bool
     */
    protected function authenticateWithResourceOwnerPasswordCredentials($username, $password)
    {
        $user = false;
        static::getLogger()->debug('Initializing OpenID Connect service');

        /** @var OAuthService $service */
        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($this->config);

        try {
            if ((bool)$this->config['oidcUseRequestPathAuthentication']) {
                static::getLogger()->debug('Retrieving an access token using request path authentication');
                $accessToken = $service->getAccessTokenWithRequestPathAuthentication($username, $password);
            } else {
                static::getLogger()->debug('Retrieving an access token using resource owner password credentials');
                $accessToken = $service->getAccessToken($username, $password);
            }
            if ($accessToken !== null) {
                static::getLogger()->debug('Access token retrieved', $accessToken->jsonSerialize());
                $user = $this->getUserFromAccessToken($service, $accessToken);
            }
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            static::getLogger()->error('Authentication has been refused by the authentication server', [
                'username' => $username,
                'message' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    /**
     * Looks up a TYPO3 user from an access token.
     *
     * @param OAuthService $service
     * @param string $accessToken
     * @return array|bool
     */
    protected function getUserFromAccessToken(OAuthService $service, $accessToken)
    {
        // Using the access token, we may look up details about the resource owner
        try {
            static::getLogger()->debug('Retrieving resource owner');
            $resourceOwner = $service->getResourceOwner($accessToken)->toArray();
            static::getLogger()->debug('Resource owner retrieved', $resourceOwner);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            static::getLogger()->error('Could not retrieve resource owner', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
        if (empty($resourceOwner['sub'])) {
            static::getLogger()->error('No "sub" found in resource owner, revoking access token');
            $service->revokeToken($accessToken);
            throw new \RuntimeException(
                'Resource owner does not have a sub part: ' . json_encode($resourceOwner)
                . '. Your access token has been revoked. Please try again.',
                1490086626
            );
        }
        $user = $this->convertResourceOwner($resourceOwner);
        return $user;
    }

    /**
     * Authenticate a user
     *
     * @param array $user
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
     * @return array|bool
     * @throws \InvalidArgumentException
     */
    protected function convertResourceOwner(array $info)
    {
        if (TYPO3_MODE === 'FE') {
            $userTable = 'fe_users';
            $userGroupTable = 'fe_groups';
        } else {
            $userTable = 'be_users';
            $userGroupTable = 'be_groups';
        }

        $user = [];
        $database = $this->getDatabaseConnection();
        $row = $database->exec_SELECTgetSingleRow(
            '*',
            $userTable,
            'tx_oidc=' . $database->fullQuoteStr($info['sub'], $userTable)
        );

        $reEnableUser = (bool)$this->config['reEnableFrontendUsers'];
        $undeleteUser = (bool)$this->config['undeleteFrontendUsers'];

        if ($row && (bool)$row['deleted'] && !$undeleteUser) {
            // User was manually deleted, it should not get automatically restored
            static::getLogger()->info('User was manually deleted, denying access', ['user' => $row]);

            return false;
        }
        if ($row && (bool)$row['disable'] && !$reEnableUser) {
            // User was manually disabled, it should not get automatically re-enabled
            static::getLogger()->info('User was manually disabled, denying access', ['user' => $row]);

            return false;
        }

        /** @var $objInstanceSaltedPW \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface */
        $objInstanceSaltedPW = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(null, TYPO3_MODE);
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 20);
        $hashedPassword = $objInstanceSaltedPW->getHashedPassword($password);

        $data = $this->applyMapping(
            $userTable,
            $info,
            $row ?: [],
            [
                'password' => $hashedPassword,
                'deleted' => 0,
                'disable' => 0,
            ]
        );

        $newUsergroups = [];
        $defaultUserGroups = GeneralUtility::intExplode(',', $this->config['usersDefaultGroup'], true);

        if ($row && !$this->config['overrideNonOIDCRoles']) {
            $currentUserGroups = GeneralUtility::intExplode(',', $row['usergroup'], true);
            if (!empty($currentUserGroups)) {
                $oidcUserGroups = $database->exec_SELECTgetRows(
                    'uid',
                    $userGroupTable,
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
        if (!empty($info[$this->config['oidcRolesClaim']])) {
            $roles = $info[$this->config['oidcRolesClaim']];
            $typo3Roles = $database->exec_SELECTgetRows(
                'uid, tx_oidc_pattern',
                $userGroupTable,
                'tx_oidc_pattern<>\'\' AND hidden=0 AND deleted=0'
            );

            if(!is_array($roles)) {
                $roles = GeneralUtility::trimExplode(',', $roles, true);
            }
            $roles = ',' . implode(',', $info[$this->config['oidcRolesClaim']]) . ',';

            foreach ($typo3Roles as $typo3Role) {
                // Convert the pattern into a proper regular expression
                $subpatterns = GeneralUtility::trimExplode('|', $typo3Role['tx_oidc_pattern'], true);
                foreach ($subpatterns as $k => $subpattern) {
                    $pattern = preg_quote($subpattern, '/');
                    $pattern = str_replace('\\*', '[^,]*', $pattern);
                    $subpatterns[$k] = $pattern;
                }
                $pattern = '/,(' . implode('|', $subpatterns) . '),/i';
                if (preg_match($pattern, $roles)) {
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
                    $userTable,
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
                'tx_oidc' => $info['sub'],
                'tx_extbase_type' => $this->config['usersExtbaseType']
            ]);
            $database->exec_INSERTquery(
                $userTable,
                $data
            );
            // Retrieve the created user from database to get all columns
            $user = $database->exec_SELECTgetSingleRow(
                '*',
                $userTable,
                'uid=' . $database->sql_insert_id()
            );
        }

        static::getLogger()->debug('Authentication user record processed', $user);

        // Hook for post-processing the user record
        $reloadUserRecord = false;
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'] as $className) {
                /** @var \Causal\Oidc\Service\ResourceOwnerHookInterface $postProcessor */
                $postProcessor = GeneralUtility::getUserObj($className);
                if ($postProcessor instanceof \Causal\Oidc\Service\ResourceOwnerHookInterface) {
                    $postProcessor->postProcessUser(TYPO3_MODE, $user, $info);
                    $reloadUserRecord = true;
                } else {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Invalid post-processing class %s. It must implement the \\Causal\\Oidc\\Service\\ResourceOwnerHookInterface interface',
                            $className
                        ),
                        1491229263
                    );
                }
            }
        }

        if ($reloadUserRecord) {
            $user = $database->exec_SELECTgetSingleRow(
                '*',
                $userTable,
                'uid=' . (int)$user['uid']
            );
            static::getLogger()->debug('User record reloaded', $user);
        }

        // We need that for the upcoming call to authUser()
        $user['tx_oidc'] = true;

        return $user;
    }

    /**
     * Merges info from OIDC to TYPO3 using a mapping configuration.
     *
     * @param string $table
     * @param array $oidc
     * @param array $typo3
     * @param array $baseData
     * @param bool $reportErrors
     * @return array
     * @see \Causal\IgLdapSsoAuth\Library\Authentication::merge()
     */
    protected function applyMapping($table, array $oidc, array $typo3, array $baseData = [], $reportErrors = false)
    {
        $out = array_merge($typo3, $baseData);
        $typoScriptKeys = [];
        $mapping = $this->getMapping($table);

        // Process every field (except "usergroup" and "parentGroup") which is not a TypoScript definition
        foreach ($mapping as $field => $value) {
            if (substr($field, -1) !== '.') {
                if ($field !== 'usergroup' && $field !== 'parentGroup') {
                    try {
                        $out = $this->mergeSimple($oidc, $out, $field, $value);
                    } catch (\UnexpectedValueException $uve) {
                        if ($reportErrors) {
                            $out['__errors'][] = $uve->getMessage();
                        }
                    }
                }
            } else {
                $typoScriptKeys[] = $field;
            }
        }

        if (count($typoScriptKeys) > 0) {
            $backupTSFE = $GLOBALS['TSFE'];

            // Advanced stdWrap methods require a valid $GLOBALS['TSFE'] => create the most lightweight one
            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
                \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class,
                $GLOBALS['TYPO3_CONF_VARS'],
                0,
                ''
            );
            $GLOBALS['TSFE']->initTemplate();
            $GLOBALS['TSFE']->renderCharset = 'utf-8';

            /** @var $contentObj \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
            $contentObj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
            $contentObj->start($oidc, '');

            // Process every TypoScript definition
            foreach ($typoScriptKeys as $typoScriptKey) {
                // Remove the trailing period to get corresponding field name
                $field = substr($typoScriptKey, 0, -1);
                $value = isset($out[$field]) ? $out[$field] : '';
                $value = $contentObj->stdWrap($value, $mapping[$typoScriptKey]);
                $out = $this->mergeSimple([$field => $value], $out, $field, $value);
            }

            // Instantiation of TypoScriptFrontendController instantiates PageRenderer which
            // sets backPath to TYPO3_mainDir which is very bad in the Backend. Therefore,
            // we must set it back to null to not get frontend-prefixed asset URLs.
            if (TYPO3_MODE === 'BE') {
                $pageRenderer = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
                $pageRenderer->setBackPath(null);
            }

            $GLOBALS['TSFE'] = $backupTSFE;
        }

        return $out;
    }

    /**
     * Replaces all OIDC markers (e.g. <cn>) with their corresponding values
     * in the OIDC data array.
     *
     * If no matching value was found in the array the marker will be removed.
     *
     * @param array $oidc
     * @param array $typo3
     * @param string $field
     * @param string $value
     * @return array Modified $typo3 array
     * @throws \UnexpectedValueException
     * @see \Causal\IgLdapSsoAuth\Library\Authentication::mergeSimple()
     * @see \Causal\IgLdapSsoAuth\Library\Authentication::replaceLdapMarkers()
     * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::getFieldVal()
     */
    protected function mergeSimple(array $oidc, array $typo3, $field, $value)
    {
        // Constant by default
        $mappedValue = $value;

        if (preg_match("`<([^$]*)>`", $value, $attribute)) {    // OIDC attribute
            $sections = !strstr($value, '//')
                ? [$value]
                : GeneralUtility::trimExplode('//', $value, true);
            $mappedValue = '';
            foreach ($sections as $sectionKey => $sectionValue) {
                preg_match_all('/<(.+?)>/', $sectionValue, $matches);

                foreach ($matches[0] as $index => $fullMatchedMarker) {
                    $oidcProperty = strtolower($matches[1][$index]);

                    if (isset($oidc[$oidcProperty])) {
                        $oidcValue = $oidc[$oidcProperty];
                        if (is_array($oidcValue)) {
                            $oidcValue = $oidcValue[0];
                        }
                        $sectionValue = str_replace($fullMatchedMarker, $oidcValue, $sectionValue);
                    } else {
                        $sectionValue = str_replace($fullMatchedMarker, '', $sectionValue);
                    }
                }

                $sections[$sectionKey] = $sectionValue;
            }

            foreach ($sections as $sectionValue) {
                if ($sectionValue !== '') {
                    $mappedValue = $sectionValue;
                    break;
                }
            }
        }

        $typo3[$field] = $mappedValue;

        return $typo3;
    }

    /**
     * Returns the mapping configuration for OIDC fields.
     *
     * @param string $table
     * @return array
     */
    protected function getMapping($table)
    {
        $mapping = [];

        $defaultMapping = [
            'username'   => '<sub>',
            'name'       => '<name>',
            'first_name' => '<Vorname>',
            'last_name'  => '<FamilienName>',
            'address'    => '<Strasse>',
            'title'      => '<Anredecode>',
            'zip'        => '<PLZ>',
            'city'       => '<Ort>',
            'country'    => '<Land>',
        ];

        if ($table === 'fe_users') {
            $setup = $this->getTypoScriptSetup();
            if (!empty($setup['plugin.']['tx_oidc.']['mapping.'][$table . '.'])) {
                $mapping = $setup['plugin.']['tx_oidc.']['mapping.'][$table . '.'];
            }
        }

        return $mapping ?: $defaultMapping;
    }

    /**
     * Returns TypoScript Setup array from current environment.
     *
     * Note: $GLOBALS['TSFE']->tmpl->setup is not yet available at this point.
     *
     * @return array the raw TypoScript setup
     */
    protected function getTypoScriptSetup()
    {
        // This is needed for the PageRepository
        $files = ['EXT:core/Configuration/TCA/pages.php'];
        foreach ($files as $file) {
            $file = GeneralUtility::getFileAbsFileName($file);
            $table = substr($file, strrpos($file, '/') + 1, -4); // strip ".php" at the end
            $GLOBALS['TCA'][$table] = include($file);
        }

        /** @var \TYPO3\CMS\Frontend\Page\PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
        $pageRepository->init(false);

        /** @var \TYPO3\CMS\Core\TypoScript\TemplateService $templateService */
        $templateService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TemplateService::class);
        $templateService->init();
        $templateService->tt_track = false;

        $currentPage = $GLOBALS['TSFE']->id;
        if ($currentPage === null) {
            // root page is not yet populated
            $localTSFE = clone $GLOBALS['TSFE'];
            $localTSFE->determineId();
            $currentPage = $localTSFE->id;
        }
        $rootLine = $pageRepository->getRootLine((int)$currentPage);
        $templateService->start($rootLine);

        $setup = $templateService->setup;
        return $setup;
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
     * Returns the extension config and tries to override endpoints if /.well-known/configuration exists on
     * the issuer's side.
     *
     * @param array $config
     * @return array
     */
    protected function getConfig(array $config)
    {
        $extConfig = $config;

        if(isset($extConfig['oidcConfigUrl'])) {
            try {
                $wellKnownConfig = $this->getWellKnownConfig(
                    $extConfig['oidcConfigUrl'],
                    (int)$extConfig['oidcWellKnownCacheLifetime']
                );
                $extConfig['oidcEndpointAuthorize'] = $wellKnownConfig['authorization_endpoint'];
                $extConfig['oidcEndpointToken'] = $wellKnownConfig['token_endpoint'];
                $extConfig['oidcEndpointUserInfo'] = $wellKnownConfig['userinfo_endpoint'];
                $extConfig['oidcEndpointRevoke'] = $wellKnownConfig['revocation_endpoint'];
                $extConfig['oidcEndpointLogout'] = $wellKnownConfig['end_session_endpoint'];
            } catch (\Exception $e) {
                static::getLogger()->error('Could not process Well-Known Config', [
                    'message' => $e->getMessage(),
                ]);
                $extConfig = $config;
            }
        }

        return $extConfig;
    }

    /**
     * Get cached config from /.well-known/configuration URL depending on lifetime
     *
     * @param string $wellKnownUrl
     * @param int $lifetime
     * @return mixed
     */
    protected function getWellKnownConfig($wellKnownUrl, $lifetime)
    {
        $cache_path = PATH_site . 'typo3temp/';
        $filename = $cache_path . md5(ExtensionManagementUtility::extPath('oidc'));

        if(file_exists($filename) && (time() - $lifetime < filemtime($filename))) {
            return json_decode(file_get_contents($filename), true);
        } else {
            $data = file_get_contents($wellKnownUrl);
            file_put_contents($filename, $data);
            return json_decode($data, true);
        }
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
