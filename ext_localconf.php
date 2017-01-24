<?php
defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    // Require 3rd-party libraries, in case TYPO3 does not run in composer mode
    @include 'phar://' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Libraries/league-oauth2-client.phar/vendor/autoload.php';
};

$boot($_EXTKEY);
unset($boot);
