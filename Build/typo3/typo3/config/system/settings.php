<?php
return [
    'BE' => [
        'debug' => true,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'charset' => 'utf8',
                'driver' => 'mysqli',
            ],
        ],
    ],
    'EXTENSIONS' => [
        'backend' => [
            'backendFavicon' => '',
            'backendLogo' => '',
            'loginBackgroundImage' => '',
            'loginFootnote' => '',
            'loginHighlightColor' => '',
            'loginLogo' => '',
            'loginLogoAlt' => '',
        ],
        'extensionmanager' => [
            'automaticInstallation' => '1',
            'offlineMode' => '0',
        ],
        'oidc' => [
            'authenticationServicePriority' => '82',
            'authenticationServiceQuality' => '80',
            'authenticationUrlRoute' => 'oidc/authentication',
            'enableCodeVerifier' => '1',
            'enableFrontendAuthentication' => '1',
            'frontendUserMustExistLocally' => '0',
            'oauthProviderFactory' => '',
            'oidcAuthorizeLanguageParameter' => 'language',
            'oidcClientKey' => 't3ext-oidc',
            'oidcClientScopes' => 'openid',
            'oidcClientSecret' => 't3ext-oidc',
            'oidcDisableCSRFProtection' => '0',
            'oidcEndpointAuthorize' => 'http://oidc.t3ext-oidc.test/connect/authorize',
            'oidcEndpointLogout' => '',
            'oidcEndpointRevoke' => 'http://oidc.t3ext-oidc.test/connect/revocation',
            'oidcEndpointToken' => 'http://oidc.t3ext-oidc.test/connect/token',
            'oidcEndpointUserInfo' => 'http://oidc.t3ext-oidc.test/connect/userinfo',
            'oidcRedirectUri' => 'https://v13.t3ext-oidc.test/login/redirect',
            'oidcRevokeAccessTokenAfterLogin' => '0',
            'oidcUseRequestPathAuthentication' => '0',
            'reEnableFrontendUsers' => '0',
            'undeleteFrontendUsers' => '0',
            'usersDefaultGroup' => '',
            'usersStoragePid' => '2',
        ],
    ],
    'FE' => [
        'cacheHash' => [
            'enforceValidation' => true,
        ],
        'debug' => true,
        'disableNoCacheParameter' => true,
        'passwordHashing' => [
            'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
            'options' => [],
        ],
    ],
    'GFX' => [
        'processor' => 'ImageMagick',
        'processor_effects' => true,
        'processor_enabled' => true,
        'processor_path' => '/usr/bin/',
    ],
    'LOG' => [
        'TYPO3' => [
            'CMS' => [
                'deprecations' => [
                    'writerConfiguration' => [
                        'notice' => [
                            'TYPO3\CMS\Core\Log\Writer\FileWriter' => [
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'MAIL' => [
        'transport' => 'sendmail',
        'transport_sendmail_command' => '/usr/sbin/sendmail -t -i',
        'transport_smtp_encrypt' => '',
        'transport_smtp_password' => '',
        'transport_smtp_server' => '',
        'transport_smtp_username' => '',
    ],
    'SYS' => [
        'UTF8filesystem' => true,
        'caching' => [
            'cacheConfigurations' => [
                'hash' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                ],
                'pages' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
                'rootline' => [
                    'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
                    'options' => [
                        'compression' => true,
                    ],
                ],
            ],
        ],
        'devIPmask' => '*',
        'displayErrors' => 1,
        'encryptionKey' => 'dff1b14d5aa12e8f6c840e205a6484f7dd0bfb0f67eea73f114543d284071c020efba9f8bf99abfa14deb1b7c2d182db',
        'exceptionalErrors' => 12290,
        'features' => [
            'frontend.cache.autoTagging' => true,
        ],
        'sitename' => 'New TYPO3 site',
    ],
];
