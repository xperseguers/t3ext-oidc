<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    // Register TypoScript
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'OpenID Connect');
    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('felogin')) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/felogin', 'OpenID Connect for felogin');
    }
};

$boot($_EXTKEY);
unset($boot);
