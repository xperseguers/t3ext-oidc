<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    // Register extension status report system
    $providerName = 'OpenID Connect Authentication';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers'][$providerName][] =
        \Causal\Oidc\Report\Status\OAuthClientStatus::class;

    // TODO: remove deprecation since TYPO3 v8
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerAjaxHandler(
        'TxOidc::callback',
        \Causal\Oidc\Service\OAuthService::class . '->callback',
        false
    );
};

$boot($_EXTKEY);
unset($boot);
