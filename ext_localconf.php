<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    // Configuration of authentication service.
    $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);

    // Service configuration
    $subTypesArr = [];
    $subTypes = '';
    if ((bool)$settings['enableFrontendAuthentication']) {
        $subTypesArr[] = 'getUserFE';
        $subTypesArr[] = 'authUserFE';
        $subTypesArr[] = 'getGroupsFE';
    }
    if ((bool)$settings['enableBackendAuthentication']) {
        $subTypesArr[] = 'getUserBE';
        $subTypesArr[] = 'authUserBE';
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
            'priority' => 80,
            'quality' => 80,
            'os' => '',
            'exec' => '',
            'className' => $authenticationClassName,
        ]
    );
};

$boot($_EXTKEY);
unset($boot);
