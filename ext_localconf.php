<?php

use Causal\Oidc\Controller\AuthenticationController;
use Causal\Oidc\Service\AuthenticationService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

$settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];

// Service configuration
$subTypesArr = [];
$subTypes = '';
if ($settings['enableFrontendAuthentication'] ?? '') {
    $subTypesArr[] = 'getUserFE';
    $subTypesArr[] = 'authUserFE';
    $subTypesArr[] = 'getGroupsFE';
}
if (is_array($subTypesArr)) {
    $subTypesArr = array_unique($subTypesArr);
    $subTypes = implode(',', $subTypesArr);
}

$authenticationClassName = AuthenticationService::class;
ExtensionManagementUtility::addService(
    'oidc',
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

ExtensionUtility::configurePlugin(
    'oidc',
    'Pi1',
    [
        AuthenticationController::class => 'connect',
    ],
    // non-cacheable actions
    [
        AuthenticationController::class => 'connect'
    ]
);

    // Add typoscript for custom login plugin
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43('oidc', null, '_login');

// Require 3rd-party libraries, in case TYPO3 does not run in composer mode
$pharFileName = ExtensionManagementUtility::extPath('oidc') . 'Libraries/league-oauth2-client.phar';
if (is_file($pharFileName)) {
    @include 'phar://' . $pharFileName . '/vendor/autoload.php';
}
