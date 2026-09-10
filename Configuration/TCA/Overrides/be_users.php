<?php

declare(strict_types=1);

defined('TYPO3') or die();

$settings = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('oidc') ?? [];

if ($settings['enableBackendAuthentication']) {
    $tempColumns = [
        'tx_oidc' => [
            'exclude' => true,
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:fe_users.tx_oidc',
            'config' => [
                'type' => 'input',
                'size' => 30,
            ],
        ],
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $tempColumns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_users', 'tx_oidc');
}
