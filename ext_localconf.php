<?php
defined('TYPO3') or die();

(static function (string $_EXTKEY) {
    if (class_exists(\TYPO3\CMS\Core\Authentication\AuthenticationService::class)
        && !class_exists(\TYPO3\CMS\Sv\AuthenticationService::class)) {
        class_alias(\TYPO3\CMS\Core\Authentication\AuthenticationService::class, \TYPO3\CMS\Sv\AuthenticationService::class);
    }

    // Configuration of authentication service
    // TODO: Use proper TYPO3 API
    $settings = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$_EXTKEY] ?? [];

    // Service configuration
    $subTypesArr = [];
    $subTypes = '';
    if ((bool)($settings['enableFrontendAuthentication'] ?? '')) {
        $subTypesArr[] = 'getUserFE';
        $subTypesArr[] = 'authUserFE';
        $subTypesArr[] = 'getGroupsFE';
    }
    if (is_array($subTypesArr)) {
        $subTypesArr = array_unique($subTypesArr);
        $subTypes = implode(',', $subTypesArr);
    }

    $authenticationClassName = \Causal\Oidc\Service\AuthenticationService::class;
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
        $_EXTKEY,
        'auth' /* sv type */,
        $authenticationClassName /* sv key */,
        [
            'title' => 'Authentication service',
            'description' => 'Authentication service for OpenID Connect.',
            'subtype' => $subTypes,
            'available' => true,
            'priority' => 82, /* will be called before default TYPO3 authentication service */
            'quality' => 80,
            'os' => '',
            'exec' => '',
            'className' => $authenticationClassName,
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        $_EXTKEY,
        'Pi1',
        [
            \Causal\Oidc\Controller\AuthenticationController::class => 'connect',
        ],
        // non-cacheable actions
        [
            \Causal\Oidc\Controller\AuthenticationController::class => 'connect'
        ]
    );

    // Add typoscript for custom login plugin
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43('oidc', null, '_login');

    // Require 3rd-party libraries, in case TYPO3 does not run in composer mode
    $pharFileName = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Libraries/league-oauth2-client.phar';
    if (is_file($pharFileName)) {
        @include 'phar://' . $pharFileName . '/vendor/autoload.php';
    }
})('oidc');
