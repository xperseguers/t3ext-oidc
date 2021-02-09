<?php
defined('TYPO3_MODE') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'oidc',
    'Configuration/TypoScript',
    'OpenID Connect'
);

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('felogin')) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'oidc',
        'Configuration/TypoScript/felogin',
        'OpenID Connect for felogin'
    );
}
