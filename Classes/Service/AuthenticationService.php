<?php

declare(strict_types=1);

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
use Causal\Oidc\Event\AuthenticationPreUserEvent;
use Causal\Oidc\Event\ModifyResourceOwnerEvent;
use Causal\Oidc\Event\ModifyUserEvent;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Context\Context;
use UnexpectedValueException;

/**
 * OpenID Connect authentication service.
 */
class AuthenticationService extends \TYPO3\CMS\Core\Authentication\AuthenticationService
{
    /**
     * 200 - authenticated and no more checking needed
     */
    private const STATUS_AUTHENTICATION_SUCCESS_BREAK = 200;

    /**
     * 100 - just go on. User is not authenticated but there's still no reason to stop
     */
    private const STATUS_AUTHENTICATION_FAILURE_CONTINUE = 100;

    /**
     * Global extension configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        $this->config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
    }

    /**
     * Finds a user.
     *
     * @return array|bool
     * @throws RuntimeException
     */
    public function getUser()
    {
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);

        $user = false;
        $request = $this->authInfo['request'] ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        $params = $request->getQueryParams()['tx_oidc'] ?? [];
        $code = $params['code'] ?? null;
        if ($code !== null) {
            $codeVerifier = null;
            if ($this->config['enableCodeVerifier']) {
                $authContext = GeneralUtility::makeInstance(OpenIdConnectService::class)->getAuthenticationContext();
                if ($authContext) {
                    $codeVerifier = $authContext->codeVerifier;
                }
            }
            $user = $this->authenticateWithAuthorizationCode($code, $codeVerifier);
        } else {
            $event = new AuthenticationPreUserEvent($this->login, $this);
            $eventDispatcher->dispatch($event);
            if (!$event->shouldProcess) {
                return false;
            }
            $this->login = $event->loginData;

            $username = $this->login['uname'] ?? null;
            if (isset($this->login['uident_text'])) {
                $password = $this->login['uident_text'];
            } elseif (isset($this->login['uident'])) {
                $password = $this->login['uident'];
            } else {
                $password = null;
            }
            if (!empty($username) && !empty($password)) {
                $user = $this->authenticateWithResourceOwnerPasswordCredentials($username, $password);
            }
        }

        if ($user) {
            // dispatch a signal (containing the user with his access token if auth was successful)
            // so other extensions can use them to make further requests to an API
            // provided by the authentication server
            /** @var Dispatcher $dispatcher */
            $dispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(Dispatcher::class);
            $dispatcher->dispatch(__CLASS__, 'getUser', [$user]);

            $event = new AuthenticationGetUserEvent($user, $this);
            $eventDispatcher->dispatch($event);
            $user = $event->getUser();
        }

        return $user;
    }

    /**
     * Authenticates a user using authorization code grant.
     *
     * @param string $code
     * @param string|null $codeVerifier
     * @return array|bool
     */
    protected function authenticateWithAuthorizationCode(string $code, ?string $codeVerifier)
    {
        $this->logger->debug('Initializing OpenID Connect service');

        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($this->config);

        // Try to get an access token using the authorization code grant
        try {
            $this->logger->debug('Retrieving an access token');
            $accessToken = $service->getAccessToken($code, null, $codeVerifier);
            $this->logger->debug('Access token retrieved', $accessToken->jsonSerialize());
        } catch (IdentityProviderException $e) {
            // Probably a "server_error", meaning the code is not valid anymore
            $this->logger->error('Possibly replay: code has been refused by the authentication server', [
                'code' => $code,
                'exception' => $e,
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
    protected function authenticateWithResourceOwnerPasswordCredentials(string $username, string $password)
    {
        $user = false;
        $this->logger->debug('Initializing OpenID Connect service');

        /** @var OAuthService $service */
        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($this->config);

        $accessToken = '';
        try {
            if ($this->config['oidcUseRequestPathAuthentication']) {
                $this->logger->debug('Retrieving an access token using request path authentication');
                $accessToken = $service->getAccessTokenWithRequestPathAuthentication($username, $password);
            } else {
                $this->logger->debug('Retrieving an access token using resource owner password credentials');
                $accessToken = $service->getAccessToken($username, $password);
            }
            if ($accessToken !== null) {
                $this->logger->debug('Access token retrieved', $accessToken->jsonSerialize());
                $user = $this->getUserFromAccessToken($service, $accessToken);
            }
        } catch (IdentityProviderException $e) {
            $this->logger->error('Authentication has been refused by the authentication server', [
                'username' => $username,
                'exception' => $e,
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
            $this->logger->debug('Retrieving resource owner');
            $resourceOwner = $service->getResourceOwner($accessToken)->toArray();
            $this->logger->debug('Resource owner retrieved', $resourceOwner);
        } catch (IdentityProviderException $e) {
            $this->logger->error('Could not retrieve resource owner', ['exception' => $e]);
            return false;
        }
        if (empty($resourceOwner['sub'])) {
            $this->logger->error('No "sub" found in resource owner, revoking access token');
            try {
                $service->revokeToken($accessToken);
            } catch (IdentityProviderException $e) {
                $this->logger->error('Could not revoke token', ['exception' => $e]);
                return false;
            }
            throw new RuntimeException(
                'Resource owner does not have a sub part: ' . json_encode($resourceOwner)
                    . '. Your access token has been revoked. Please try again.',
                1490086626
            );
        }

        $event = new ModifyResourceOwnerEvent($resourceOwner, $this);
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($event);

        $user = $this->convertResourceOwner($event->getResourceOwner());

        if ($this->config['oidcRevokeAccessTokenAfterLogin']) {
            try {
                $service->revokeToken($accessToken);
            } catch (IdentityProviderException $e) {
                $this->logger->error('Could not revoke token', ['exception' => $e]);
            }
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
        // missing access token means the actual OIDC authentication step in the `getUser` method failed
        // or has neven been executed, if the user was discovered by some other authentication service
        if (!isset($user['accessToken'])) {
            return static::STATUS_AUTHENTICATION_FAILURE_CONTINUE;
        }

        // this is not a valid user authenticated via OIDC
        if (empty($user['tx_oidc'])) {
            return static::STATUS_AUTHENTICATION_FAILURE_CONTINUE;
        }

        return static::STATUS_AUTHENTICATION_SUCCESS_BREAK;
    }

    /**
     * Converts a resource owner into a TYPO3 Frontend user.
     *
     * @param array $info
     * @return array|bool
     */
    protected function convertResourceOwner(array $info)
    {
        $mode = $this->authInfo['loginType'];
        $userTable = $this->db_user['table'];
        $userGroupTable = $mode === 'FE' ? 'fe_groups' : 'be_groups';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($userTable);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from($userTable)
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter(
                    GeneralUtility::intExplode(',', $this->config['usersStoragePid']),
                    Connection::PARAM_INT_ARRAY
                )),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('tx_oidc', $queryBuilder->createNamedParameter($info['sub'])),
                    $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($info['email']))
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        $reEnableUser = (bool)$this->config['reEnableFrontendUsers'];
        $undeleteUser = (bool)$this->config['undeleteFrontendUsers'];
        $frontendUserMustExistLocally = (bool)$this->config['frontendUserMustExistLocally'];

        if (!empty($row) && $row['deleted'] && !$undeleteUser) {
            // User was manually deleted, it should not get automatically restored
            $this->logger->info('User was manually deleted, denying access', ['user' => $row]);

            return false;
        }
        if (!empty($row) && $row['disable'] && !$reEnableUser) {
            // User was manually disabled, it should not get automatically re-enabled
            $this->logger->info('User was manually disabled, denying access', ['user' => $row]);

            return false;
        }
        if (empty($row) && $frontendUserMustExistLocally) {
            // User does not exist locally, it should not be created on-the-fly
            $this->logger->info('User does not exist locally, denying access', ['info' => $info]);

            return false;
        }

        $data = $this->applyMapping(
            $userTable,
            $info,
            $row ?: [],
            [
                'tx_oidc' => $info['sub'],
                'password' => $this->generatePassword(),
                'deleted' => 0,
                'disable' => 0,
            ]
        );

        // preserve username and password for existing users
        if ($row) {
            unset($data['username']);
            unset($data['email']);
            unset($data['password']);
        }

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
                    ->executeQuery()
                    ->fetchAllAssociative();

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
                ->executeQuery()
                ->fetchAllAssociative();

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
        $event = new AuthenticationGetUserGroupsEvent($userGroupTable, $newUserGroups, $info, $this);
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($event);
        if ($newUserGroups !== $event->getUserGroups()) {
            $this->logger->debug('Got customized user groups by AuthenticationGetUserGroupsEvent', [
                'previous' => implode(',', $newUserGroups),
                'new' => implode(',', $event->getUserGroups()),
            ]);
            $newUserGroups = $event->getUserGroups();
        }

        $tableConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($userTable);

        if (!empty($row)) { // fe_users record already exists => update it
            $this->logger->info('Detected a returning user');
            $data['usergroup'] = implode(',', $newUserGroups);
            $user = array_merge($row, $data);

            $event = new ModifyUserEvent($user, $this);
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $eventDispatcher->dispatch($event);
            $user = $event->getUser();

            if ($user != $row) {
                $this->logger->debug('Updating existing user', [
                    'old' => $row,
                    'new' => $user,
                ]);
                $user['tstamp'] = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
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
                $this->logger->info('User has no associated local TYPO3 user group, denying access', ['user' => $row]);

                return false;
            }
            $this->logger->info('New user detected, creating a TYPO3 user');
            $data = array_merge($data, [
                'pid' => GeneralUtility::intExplode(',', $this->config['usersStoragePid'], true)[0],
                'usergroup' => implode(',', $newUserGroups),
                'crdate' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
                'tx_oidc' => $info['sub'],
            ]);

            $event = new ModifyUserEvent($data, $this);
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $eventDispatcher->dispatch($event);
            $data = $event->getUser();

            $tableConnection->insert(
                $userTable,
                $data
            );
            $userUid = $tableConnection->lastInsertId();
            // Retrieve the created user from database to get all columns
            $user = $this->getUserByUidAndTable((int)$userUid, $userTable);
        }

        $this->logger->debug('Authentication user record processed', $user);

        // Hook for post-processing the user record
        $reloadUserRecord = false;
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['oidc']['resourceOwner'] as $className) {
                /** @var ResourceOwnerHookInterface $postProcessor */
                $postProcessor = GeneralUtility::makeInstance($className);
                if ($postProcessor instanceof ResourceOwnerHookInterface) {
                    $postProcessor->postProcessUser($mode, $user, $info);
                    $reloadUserRecord = true;
                } else {
                    throw new InvalidArgumentException(
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
            $this->logger->debug('User record reloaded', $user);
        }

        return $user;
    }

    protected function getUserByUidAndTable(int $uid, string $table): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryResult = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery();

        $user = $queryResult->fetchAssociative();
        if (!is_array($user) || $user === []) {
            throw new LogicException('The user record could not be obtained', 1643452557);
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
     */
    protected function applyMapping(string $table, array $oidc, array $typo3, array $baseData = [], bool $reportErrors = false): array
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
                    } catch (UnexpectedValueException $uve) {
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
            $backupTSFE = $GLOBALS['TSFE'] ?? null;

            $GLOBALS['TSFE'] = $this->getLocalTSFE();

            /** @var $contentObj ContentObjectRenderer */
            $contentObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentObj->start($oidc);

            // Process every TypoScript definition
            foreach ($typoScriptKeys as $typoScriptKey) {
                // Remove the trailing period to get corresponding field name
                $field = substr($typoScriptKey, 0, -1);
                $value = $out[$field] ?? '';
                $value = $contentObj->stdWrap($value, $mapping[$typoScriptKey]);
                $out = $this->mergeSimple([$field => $value], $out, $field, $value);
            }

            if ($backupTSFE) {
                $GLOBALS['TSFE'] = $backupTSFE;
            }
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
     * @throws UnexpectedValueException
     * @see ContentObjectRenderer::getFieldVal
     */
    protected function mergeSimple(array $oidc, array $typo3, string $field, string $value): array
    {
        // Constant by default
        $mappedValue = $value;

        if (preg_match("`<([^$]*)>`", $value)) {    // OIDC attribute
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
    protected function getMapping(string $table): array
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
    protected function getTypoScriptSetup(): array
    {
        // This is needed for the PageRepository
        $files = ['EXT:core/Configuration/TCA/pages.php'];
        foreach ($files as $file) {
            $file = GeneralUtility::getFileAbsFileName($file);
            $table = substr($file, strrpos($file, '/') + 1, -4); // strip ".php" at the end
            $GLOBALS['TCA'][$table] = include($file);
        }

        $localTSFE = $this->getLocalTSFE();

        $templateService = GeneralUtility::makeInstance(TemplateService::class, null, null, $localTSFE);

        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $localTSFE->getPageArguments()->getPageId())->get();
        $templateService->start($rootLine);
        return $templateService->setup;
    }

    protected function generatePassword(): string
    {
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 20);

        $passwordHashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        try {
            $objInstanceSaltedPW = $passwordHashFactory->getDefaultHashInstance($this->authInfo['loginType']);
        } catch (InvalidPasswordHashException $e) {
            return '';
        }
        return $objInstanceSaltedPW->getHashedPassword($password);
    }

    protected function getLocalTSFE(): TypoScriptFrontendController
    {
        $request = $this->authInfo['request'] ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $routeResult = $siteMatcher->matchRequest($request);
        if ($routeResult instanceof SiteRouteResult) {
            $site = $routeResult->getSite();
            if ($site instanceof Site) {
                try {
                    $pageArguments = $site->getRouter()->matchRequest($request, $routeResult);
                    if ($pageArguments instanceof PageArguments) {
                        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
                        $context = GeneralUtility::makeInstance(Context::class);
                        return GeneralUtility::makeInstance(
                            TypoScriptFrontendController::class,
                            $context,
                            $site,
                            $routeResult->getLanguage(),
                            $pageArguments,
                            $frontendUser
                        );
                    }
                } catch (RouteNotFoundException $e) {
                }
            }
        }
        throw new InvalidArgumentException('Failed to initialize TSFE');
    }
}
