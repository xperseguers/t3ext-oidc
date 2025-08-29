<?php
defined('TYPO3') or die();

$settings = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('oidc') ?? [];

$tempColumns = [
    'tx_oidc' => [
        'exclude' => true,
        'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:be_users.tx_oidc',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'readOnly' => !($settings['userMustExistLocally'] ?? ''),
        ]
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_users', 'tx_oidc');
