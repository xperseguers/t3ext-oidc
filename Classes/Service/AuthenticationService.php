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

use Causal\Oidc\Event\AuthenticationGetUserEvent;
use Causal\Oidc\Event\AuthenticationGetUserGroupsEvent;
use League\OAuth2\Client\Token\AccessToken;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Context\Context;

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
     * Global extension configuration
     *
     * @var array
     */
    protected $config;

    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;
        if (version_compare($typo3Branch, '9.0', '<')) {
            $this->config = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc']);
        } else {
            $this->config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oidc'] ?? [];
        }
    }

    protected function getCodeVerifierFromSession()
    {
        if (session_id() === '') {
            session_start();
        }
        return @$_SESSION['oidc_code_verifier'];
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
            $codeVerifier = null;
            if ($this->config['enableCodeVerifier']) {
                $codeVerifier = $this->getCodeVerifierFromSession();
            }
            $user = $this->authenticateWithAuhorizationCode($code, $codeVerifier);
        } elseif (!(empty($username) || empty($password))) {
            $user = $this->authenticateWithResourceOwnerPasswordCredentials($username, $password);
        }

        // dispatch a signal (containing the user with his access token if auth was successful)
        // so other extensions can use them to make further requests to an API
        // provided by the authentication server
        $dispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(Dispatcher::class);
        $dispatcher->dispatch(__CLASS__, 'getUser', ['user' => $user]);

        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;

        if (version_compare($typo3Branch, '10.2', '>=')) {
            $event = new AuthenticationGetUserEvent($user);
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $eventDispatcher->dispatch($event);
            $user = $event->getUser();
        }

        if (is_array($user)) {
            unset($user['accessToken']);
        }

        return $user;
    }

    /**
     * Authenticates a user using authorization code grant.
     *
     * @param string $code
     * @return array|bool
     */
    protected function authenticateWithAuhorizationCode($code, $codeVerifier = null)
    {
        static::getLogger()->debug('Initializing OpenID Connect service');

        /** @var OAuthService $service */
        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($this->config);

        // Try to get an access token using the authorization code grant
        try {
            static::getLogger()->debug('Retrieving an access token');
            $accessToken = $service->getAccessToken($code, null, $codeVerifier);
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
        if (is_array($user)) {
            $user['accessToken'] = $accessToken;
        }

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

        if (is_array($user)) {
            $user['accessToken'] = $accessToken;
        }

        return $user;
    }

    /**
     * Looks up a TYPO3 user from an access token.
     *
     * @param OAuthService $service
     * @param AccessToken $accessToken
     * @return array|bool
     */
    protected function getUserFromAccessToken(OAuthService $service, AccessToken $accessToken)
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

        if ($this->config['oidcRevokeAccessTokenAfterLogin']) {
            $service->revokeToken($accessToken);
        }

        return $user;
    }

    /**
     * Authenticates a user
     *
     * @param array $user
     * @return int
     */
    public function authUser(array $user): int
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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($userTable);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from($userTable)
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter(
                    GeneralUtility::intExplode(',', $this->config['usersStoragePid']),
                    \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY
                )),
                $queryBuilder->expr()->eq('tx_oidc', $queryBuilder->createNamedParameter($info['sub'], \PDO::PARAM_STR))
            )
            ->execute()
            ->fetch();

        $reEnableUser = (bool)$this->config['reEnableFrontendUsers'];
        $undeleteUser = (bool)$this->config['undeleteFrontendUsers'];
        $frontendUserMustExistLocally = (bool)$this->config['frontendUserMustExistLocally'];

        if (!empty($row) && (bool)$row['deleted'] && !$undeleteUser) {
            // User was manually deleted, it should not get automatically restored
            static::getLogger()->info('User was manually deleted, denying access', ['user' => $row]);

            return false;
        }
        if (!empty($row) && (bool)$row['disable'] && !$reEnableUser) {
            // User was manually disabled, it should not get automatically re-enabled
            static::getLogger()->info('User was manually disabled, denying access', ['user' => $row]);

            return false;
        }
        if (empty($row) && $frontendUserMustExistLocally) {
            // User does not exist locally, it should not be created on-the-fly
            static::getLogger()->info('User does not exist locally, denying access', ['info' => $info]);

            return false;
        }

        /** @var $objInstanceSaltedPW \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface */
        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;
        if (version_compare($typo3Branch, '9.5', '>=')) {
            $passwordHashFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory::class);
            $objInstanceSaltedPW = $passwordHashFactory->getDefaultHashInstance(TYPO3_MODE);
        } else {
            $objInstanceSaltedPW = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(null, TYPO3_MODE);
        }
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

        $newUserGroups = [];
        $defaultUserGroups = GeneralUtility::intExplode(',', $this->config['usersDefaultGroup']);

        if (!empty($row)) {
            $currentUserGroups = GeneralUtility::intExplode(',', $row['usergroup'], true);
            if (!empty($currentUserGroups)) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable($userGroupTable);
                $queryBuilder->getRestrictions()
                    ->removeByType(HiddenRestriction::class)
                    ->removeByType(StartTimeRestriction::class)
                    ->removeByType(EndTimeRestriction::class);

                $groups = $queryBuilder
                    ->select('uid')
                    ->from($userGroupTable)
                    ->where(
                        $queryBuilder->expr()->in('uid', $currentUserGroups),
                        $queryBuilder->expr()->neq('tx_oidc_pattern', $queryBuilder->quote(''))
                    )
                    ->execute()
                    ->fetchAll();

                $oidcUserGroups = [];
                foreach ($groups as $group) {
                    $oidcUserGroups[] = $group['uid'];
                }

                // Remove OIDC-related groups
                $newUserGroups = array_diff($currentUserGroups, $oidcUserGroups);
            }
        }

        // Map OIDC roles to TYPO3 user groups
        if (!empty($info['Roles'])) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($userGroupTable);
            $typo3Roles = $queryBuilder
                ->select('uid', 'tx_oidc_pattern')
                ->from($userGroupTable)
                ->where(
                    $queryBuilder->expr()->neq('tx_oidc_pattern', $queryBuilder->quote(''))
                )
                ->execute()
                ->fetchAll();

            $roles = is_array($info['Roles']) ? $info['Roles'] : GeneralUtility::trimExplode(',', $info['Roles'], true);
            $roles = ',' . implode(',', $roles) . ',';

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
                    $newUserGroups[] = (int)$typo3Role['uid'];
                }
            }
        }

        // Add default user groups
        $newUserGroups = array_unique(array_merge($newUserGroups, $defaultUserGroups));

        // emit a generic groups mapping event
        // to customize the groups if the resource structure pattern "Roles" does not fit
        if (version_compare($typo3Branch, '10.2', '>=')) {
            $event = new AuthenticationGetUserGroupsEvent($userGroupTable, $newUserGroups, $info);
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $eventDispatcher->dispatch($event);
            if ($newUserGroups !== $event->getUserGroups()) {
                self::getLogger()->debug('Got customized user groups by AuthenticationGetUserGroupsEvent', [
                    'previous' => implode(',', $newUserGroups),
                    'new' => implode(',', $event->getUserGroups()),
                ]);
                $newUserGroups = $event->getUserGroups();
            }
        }

        $tableConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($userTable);

        if (!empty($row)) { // fe_users record already exists => update it
            static::getLogger()->info('Detected a returning user');
            $data['usergroup'] = implode(',', $newUserGroups);
            $user = array_merge($row, $data);
            if ($user != $row) {
                static::getLogger()->debug('Updating existing user', [
                    'old' => $row,
                    'new' => $user,
                ]);
                $user['tstamp'] = $GLOBALS['EXEC_TIME'];
                $tableConnection->update(
                    $userTable,
                    $user,
                    [
                        'uid' => $user['uid'],
                    ]
                );
            }
        } else {    // fe_users record does not already exist => create it
            if (empty($newUserGroups)) {
                // Somehow the user is not mapped to any local user group, we should not create the record
                static::getLogger()->info('User has no associated local TYPO3 user group, denying access', ['user' => $row]);

                return false;
            }
            static::getLogger()->info('New user detected, creating a TYPO3 user');
            $data = array_merge($data, [
                'pid' => GeneralUtility::intExplode(',', $this->config['usersStoragePid'], true)[0],
                'usergroup' => implode(',', $newUserGroups),
                'crdate' => $GLOBALS['EXEC_TIME'],
                'tx_oidc' => $info['sub'],
            ]);
            $tableConnection->insert(
                $userTable,
                $data
            );
            $userUid = $tableConnection->lastInsertId();
            // Retrieve the created user from database to get all columns
            $user = $this->getUserByUidAndTable((int)$userUid, $userTable);
        }

        static::getLogger()->debug('Authentication user record processed', $user);

        // Hook for post-processing the user record
        $reloadUserRecord = false;
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'] as $className) {
                /** @var \Causal\Oidc\Service\ResourceOwnerHookInterface $postProcessor */
                $postProcessor = GeneralUtility::makeInstance($className);
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
            $user = $this->getUserByUidAndTable((int)$user['uid'], $userTable);
            static::getLogger()->debug('User record reloaded', $user);
        }

        // We need that for the upcoming call to authUser()
        $user['tx_oidc'] = true;

        return $user;
    }

    protected function getUserByUidAndTable(int $uid, string $table): array
    {
        $user = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryResult = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
            )
            ->execute();
        if ($queryResult instanceof \Doctrine\DBAL\ForwardCompatibility\Result) {
            $user = $queryResult->fetchAssociative();
        }
        if ($user === [] && $queryResult instanceof \Doctrine\DBAL\Driver\Statement) {
            $user = $queryResult->fetch();
        }
        if (!is_array($user) || $user === []) {
            throw new \LogicException('The user record could not be obtained', 1643452557);
        }
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

        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;

        if (version_compare($typo3Branch, '10.0', '>=')) {
            /** @var ServerRequestInterface $request */
            $request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
            /** @var SiteMatcher $siteMatcher */
            $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
            $routeResult = $siteMatcher->matchRequest($request);
            $site = $routeResult->getSite();
            $pageArguments = $site->getRouter()->matchRequest($request, $routeResult);
            $currentPage = $pageArguments->getPageId();

            $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $context = GeneralUtility::makeInstance(Context::class);
            $localTSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $routeResult->getLanguage(), $pageArguments, $frontendUser);

            /** @var TemplateService $templateService */
            $templateService = GeneralUtility::makeInstance(TemplateService::class, null, null, $localTSFE);

            $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, (int)$currentPage)->get();
            $templateService->start($rootLine);
            $setup = $templateService->setup;
            return $setup;
        }

        /** @var \TYPO3\CMS\Frontend\Page\PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
        if (version_compare($typo3Branch, '9.0', '<')) {
            $pageRepository->init(false);
        }

        /** @var TemplateService $templateService */
        $templateService = GeneralUtility::makeInstance(TemplateService::class);
        if (version_compare($typo3Branch, '9.0', '<')) {
            $templateService->init();
        }
        $templateService->tt_track = false;

        $currentPage = $GLOBALS['TSFE']->id;
        if ($currentPage === null) {
            // root page is not yet populated
            $localTSFE = clone $GLOBALS['TSFE'];
            if (version_compare($typo3Branch, '9.5', '>=')) {
                $localTSFE->fe_user = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            }
            $localTSFE->determineId();
            $currentPage = $localTSFE->id;
        }
        if (version_compare($typo3Branch, '9.5', '>=')) {
            $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, (int)$currentPage)->get();
        } else {
            $rootLine = $pageRepository->getRootLine((int)$currentPage);
        }
        $templateService->start($rootLine);

        $setup = $templateService->setup;
        return $setup;
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
