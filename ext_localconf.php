<?php

declare(strict_types=1);

use Causal\Oidc\Hooks\DataHandlerOidc;
use Causal\Oidc\Service\AuthenticationService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerOidc::class;

$settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];

// Service configuration
$subTypes = '';
if ($settings['enableFrontendAuthentication'] ?? '') {
    $subTypesArr = [
        'getUserFE',
        'authUserFE',
        'getGroupsFE',
    ];
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
        'priority' => (int)($settings['authenticationServicePriority'] ?? 82),
        'quality' => (int)($settings['authenticationServiceQuality'] ?? 80),
        'os' => '',
        'exec' => '',
        'className' => $authenticationClassName,
    ]
);

// Add typoscript for custom login plugin
ExtensionManagementUtility::addPItoST43('oidc', '', '_login');

// Require 3rd-party libraries, in case TYPO3 does not run in composer mode
$pharFileName = ExtensionManagementUtility::extPath('oidc') . 'Libraries/league-oauth2-client.phar';
if (is_file($pharFileName)) {
    @include 'phar://' . $pharFileName . '/vendor/autoload.php';
}
