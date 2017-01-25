<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application',
        'label' => 'name',
        'adminOnly' => 1,
        'rootLevel' => 1,
        'dividers2tabs' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:oidc/Resources/Public/Icons/icon_tx_oidc_application.png',
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden, name, domains, oauth_client_key, oauth_client_secret, endpoint_authorize,
                                endpoint_token, endpoint_revoke, endpoint_userinfo, endpoint_checksession,
                                endpoint_logout',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                    hidden, name, domains,
                --div--;LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tabs.oauth2,
                    oauth_client_key, oauth_client_secret,
                --div--;LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tabs.endpoint,
                    endpoint_authorize, endpoint_token, endpoint_revoke, endpoint_userinfo, endpoint_checksession,
                    endpoint_logout
                    '
        ],
    ],
    'palettes' => [],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => '0'
            ]
        ],
        'name' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
            ]
        ],
        'domains' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.domains',
            'config' => [
                'type' => 'select',
                'foreign_table' => 'sys_domain',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'wizards' => [
                    'suggest' => [
                        'type' => 'suggest'
                    ],
                ],
            ]
        ],
        'oauth_client_key' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.oauth_client_key',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
            ]
        ],
        'oauth_client_secret' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.oauth_client_secret',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
            ]
        ],
        'endpoint_authorize' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_authorize',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/authorize',   // TODO: Remove for public, generic release
            ]
        ],
        'endpoint_token' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_token',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/token',   // TODO: Remove for public, generic release
            ]
        ],
        'endpoint_revoke' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_revoke',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/revoke',   // TODO: Remove for public, generic release
            ]
        ],
        'endpoint_userinfo' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_userinfo',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/userinfo',   // TODO: Remove for public, generic release
            ]
        ],
        'endpoint_checksession' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_checksession',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/checksession',   // TODO: Remove for public, generic release
            ]
        ],
        'endpoint_logout' => [
            'label' => 'LLL:EXT:oidc/Resources/Private/Language/locallang_db.xlf:tx_oidc_application.endpoint_logout',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'required,trim',
                'default' => 'https://ids02.sac-cas.ch:443/oauth2/logout',   // TODO: Remove for public, generic release
            ]
        ],
    ]
];
