<?php
defined('TYPO3') or die();

$settings = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('oidc') ?? [];

$tempColumns = [
    'tx_oidc' => [
        'exclude' => true,
        'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:fe_users.tx_oidc',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'readOnly' => !($settings['frontendUserMustExistLocally'] ?? ''),
        ]
    ],
     'tx_oidc_info' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:fe_users.tx_oidc_info',
        'config' => [
            'type' => 'text',
            'cols' => 30,
            'rows' => 6,
            'readOnly' => 1,
        ]
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', '--div--;OpenID Connect,tx_oidc, tx_oidc_info');
