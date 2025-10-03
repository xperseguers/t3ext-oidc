<?php

(function () {
    // configure TYPO3
    $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS'], [
        'DB' => [
            'Connections' => [
                'Default' => [
                    'dbname' => getenv('TYPO3_DB_DBNAME'),
                    'host' => getenv('TYPO3_DB_HOST'),
                    'password' => getenv('TYPO3_DB_PASSWORD'),
                    'port' => getenv('TYPO3_DB_PORT'),
                    'user' => getenv('TYPO3_DB_USERNAME'),
                ],
            ],
        ],
        'GFX' => [
            'processor_path' => getenv('TYPO3_GFX_PROCESSOR_PATH'),
            'processor_path_lzw' => getenv('TYPO3_GFX_PROCESSOR_PATH_LZW'),
        ],
    ]);

    // SMTP mailserver
    if (getenv('TYPO3_SMTP_HOST')) {
        $port = getenv('TYPO3_SMTP_PORT') ? (int)getenv('TYPO3_SMTP_PORT') : 25;
        $server = sprintf('%s:%s', getenv('TYPO3_SMTP_HOST'), $port);

        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = array_replace($GLOBALS['TYPO3_CONF_VARS']['MAIL'], [
            'transport' => 'smtp',
            'transport_smtp_server' => $server,
        ]);

        $properties = ['encrypt', 'username', 'password'];
        foreach ($properties as $property) {
            $envVariableName = 'TYPO3_SMTP_' . strtoupper($property);
            $configurationKey = 'transport_smtp_' . strtolower($property);

            $envValue = getenv($envVariableName);
            if ($envValue !== false) {
                $GLOBALS['TYPO3_CONF_VARS']['MAIL'][$configurationKey] = $envValue;
            }
        }
    }

    // install tool
    if (getenv('TYPO3_INSTALL_TOOL_PASSWORD')) {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword'] = getenv('TYPO3_INSTALL_TOOL_PASSWORD');
    }

    // Development configuration
    if (\TYPO3\CMS\Core\Core\Environment::getContext()->isDevelopment()) {
        foreach (glob(TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/../configuration/*.php') as $configurationFile) {
            require_once($configurationFile);
        }
    }

    $GLOBALS['TYPO3_CONF_VARS']['BE']['passwordPolicy'] = '';

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oidc'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oidc'] ?? [], [
        'enableFrontendAuthentication' => getenv('TYPO3_OIDC_ENABLE_FRONTEND_AUTHENTICATION'),
        'oidcClientKey' => getenv('TYPO3_OIDC_OIDC_CLIENT_KEY'),
        'oidcClientScopes' => getenv('TYPO3_OIDC_OIDC_CLIENT_SCOPES'),
        'oidcClientSecret' => getenv('TYPO3_OIDC_OIDC_CLIENT_SECRET'),
        'oidcEndpointAuthorize' => getenv('TYPO3_OIDC_OIDC_ENDPOINT_AUTHORIZE'),
        'oidcEndpointLogout' => getenv('TYPO3_OIDC_OIDC_ENDPOINT_LOGOUT'),
        'oidcEndpointRevoke' => getenv('TYPO3_OIDC_OIDC_ENDPOINTREVOKE'),
        'oidcEndpointToken' => getenv('TYPO3_OIDC_OIDC_ENDPOINT_TOKEN'),
        'oidcEndpointUserInfo' => getenv('TYPO3_OIDC_OIDC_ENDPOINT_USERINFO'),
        'oidcRedirectUri' => getenv('TYPO3_OIDC_OIDC_REDIRECT_URI'),
        'enableCodeVerifier' => getenv('TYPO3_OIDC_ENABLE_CODE_VERIFIER'),
    ]);
})();
