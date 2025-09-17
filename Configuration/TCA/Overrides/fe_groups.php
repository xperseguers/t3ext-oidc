<?php

defined('TYPO3') or die();

$tempColumns = [
    'tx_oidc_pattern' => [
        'exclude' => true,
        'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:fe_groups.tx_oidc_pattern',
        'config' => [
            'type' => 'input',
            'size' => 30,
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_groups', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_groups', 'tx_oidc_pattern');
