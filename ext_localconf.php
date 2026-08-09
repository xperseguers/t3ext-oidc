<?php

declare(strict_types=1);

use Causal\Oidc\LoginProvider\OidcLoginProvider;
use Causal\Oidc\LoginProvider\OidcLoginProvider14;
use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\AuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_oidc[code]';

$settings = GeneralUtility::makeInstance(OidcConfiguration::class);

// Service configuration
$subTypes = array_merge(
    ($settings->enableFrontendAuthentication) ? [
        'getUserFE',
        'authUserFE',
        'getGroupsFE',
    ] : [],
    ($settings->enableBackendAuthentication) ? [
        'getUserBE',
        'authUserBE',
    ] : [],
);

$authenticationClassName = AuthenticationService::class;
ExtensionManagementUtility::addService(
    'oidc',
    'auth' /* sv type */,
    $authenticationClassName /* sv key */,
    [
        'title' => 'Authentication service',
        'description' => 'Authentication service for OpenID Connect.',
        'subtype' => implode(',', $subTypes),
        'available' => true,
        'priority' => $settings->authenticationServicePriority,
        'quality' => $settings->authenticationServiceQuality,
        'os' => '',
        'exec' => '',
        'className' => $authenticationClassName,
    ]
);

// Require 3rd-party libraries, in case TYPO3 does not run in composer mode
$pharFileName = ExtensionManagementUtility::extPath('oidc') . 'Libraries/league-oauth2-client.phar';
if (is_file($pharFileName)) {
    @include 'phar://' . $pharFileName . '/vendor/autoload.php';
}

if ($settings->enableBackendAuthentication) {
    if (new \TYPO3\CMS\Core\Information\Typo3Version()->getMajorVersion() < 14) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][OidcLoginProvider::IDENTIFIER] = [
            'provider' => OidcLoginProvider::class,
            'sorting' => 50,
            'iconIdentifier' => 'actions-key',
            'label' => 'OIDC',
        ];
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][OidcLoginProvider14::IDENTIFIER] = [
            'provider' => OidcLoginProvider14::class,
            'sorting' => 50,
            'iconIdentifier' => 'actions-key',
            'label' => 'OIDC',
        ];
    }
}
