<?php

declare(strict_types=1);

use Causal\Oidc\Hooks\DataHandlerOidc;
use Causal\Oidc\LoginProvider\OidcLoginProvider;
use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\AuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerOidc::class;

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

// Add typoscript for custom login plugin
ExtensionManagementUtility::addTypoScript('oidc', 'setup', '
# Setting oidc plugin TypoScript
plugin.tx_oidc_login = USER_INT
plugin.tx_oidc_login {
    userFunc = Causal\Oidc\Controller\LoginController->login
    defaultRedirectPid =
    # Additional URL parameters for the authorization URL of the identity server
    authorizationUrlOptions {
        # login_theme = dark
    }
}
');
ExtensionManagementUtility::addTypoScript('oidc', 'setup', '
# Setting oidc plugin TypoScript
tt_content.list.20.oidc_login =< plugin.tx_oidc_login
', 'defaultContentRendering');

// Require 3rd-party libraries, in case TYPO3 does not run in composer mode
$pharFileName = ExtensionManagementUtility::extPath('oidc') . 'Libraries/league-oauth2-client.phar';
if (is_file($pharFileName)) {
    @include 'phar://' . $pharFileName . '/vendor/autoload.php';
}

if ($settings->enableBackendAuthentication) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][OidcLoginProvider::IDENTIFIER] = [
        'provider' => OidcLoginProvider::class,
        'sorting' => 50,
        'iconIdentifier' => 'actions-key',
        'label' => 'OIDC',
    ];
}
