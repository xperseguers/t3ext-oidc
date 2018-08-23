<?php

defined('TYPO3_MODE') or die('Access denied');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    [
        'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tt_content.oidc_login',
        'oidc_login',
    ],
    'list_type',
    'oidc'
);
