<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addPlugin(
    new SelectItem(
        type: 'select',
        label: 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tt_content.oidc_login',
        value: 'oidc_login',
        icon: 'ext-oidc-icon'
    ),
    'CType',
    'oidc'
);
