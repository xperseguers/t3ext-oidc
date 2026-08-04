<?php

use Causal\Oidc\OidcConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$settings = GeneralUtility::makeInstance(OidcConfiguration::class);

$tempColumns = [
    'tx_oidc' => [
        'exclude' => true,
        'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:fe_users.tx_oidc',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'readOnly' => !($settings->frontendUserMustExistLocally ?? ''),
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'tx_oidc');
