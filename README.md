# OpenID Connect integration for TYPO3

## Introduction

Using this TYPO3 extension enables your TYPO3 instance to use external identity providers (IdPs)
to authenticate frontend and/or backend users using the OpenID Connect protocol.

This allows to leverage Single-Sign-On (SSO) scenarios.

After installing and configuring the extension, users will have a dedicated button
to login via the configured IdP and will be returned to TYPO3 with an active session.

Various configuration options allow to map information retrieved from the IdP to
the TYPO3 user record. For instance: Name, contact data, groups/roles

## Identity provider examples

This is an incomplete list of well-known identity providers:

- Keycloak
- Authentik
- WSO2 Identity Server
- Microsoft EntraID
- Google
- GitHub
- Gitlab
- ID Austria

## Usage for frontend

### Next to ext:felogin

The login can be triggered by a button, which can be placed next to your existing
felogin form. So you can use both login-mechanisms in parallel.

After the login process, the user will be redirected according to the rules
defined in the felogin plugin on the same page.

### Direct OIDC Login

If OpenID Connect is your only means of frontend login, you can use the included
"OIDC Login" plugin. Add it to your login page, where you would normally put the
felogin form. After adding the OIDC Login plugin, requests to the login page will
immediately be redirected to the identity provider.

After the login process, the user will be redirected:

- The OIDC Login supports the same `redirect_url` parameter as the felogin box
- If no parameter is set, OIDC Login will redirect the user to the page
  configured at `plugin.tx_oidc_login.defaultRedirectPid`.
- If that configuration is not set either, the user will be redirected to '/'.

## Usage for backend

If enabled, the extension provides a login provider for backend of the TYPO3 instance.

## Configuration

The extension requires at least a configuration for how user information from the IdP
is mapped to TYPO3 user records fe_users/be_users.

### Mapping user fields

- Configuration is done through TypoScript within the keys
  `plugin.tx_oidc.mapping.fe_users` and  `plugin.tx_oidc.mapping.be_users`
- Information about the user from OIDC attributes (ID Token or UserInfo-endpoint) will be recognized by the specific characters `<>`:

  ```typo3_typoscript
  email = <mail>
  ```

- You may combine multiple markers as well, e.g.,

  ```typo3_typoscript
  name = <family_name>, <given_name>
  ```

- Support for [stdWrap](https://docs.typo3.org/permalink/t3tsref:stdwrap) in field definition, e.g.,

  ```typo3_typoscript
  name = <name>
  name.wrap = |-OIDC
  ```

- Support for [TypoScript data fallback](https://docs.typo3.org/permalink/t3tsref:data-type-gettext)
  (`//`). This will check multiple field names and return the first one yielding
  some non-empty value. E.g.,

  ```typo3_typoscript
  username = <sub> // <contact_number> // <emailaddress> // <benutzername>
  ```

### Mapping user groups

It is possible to assign TYPO3 user groups to a user based on the `Roles` attribute
sent via OIDC.

Steps to follow:

- Create your groups within TYPO3
- Use the additional pattern-field within the group record to relate each group to Roles within OpenID Connect
- Local TYPO3 groups (not related to some role) will not be added or removed from a user record
- Default TYPO3 group(s) as configured in the extension's global configuration will always be added

### OIDC Login plugin TypoScript configuration

- `plugin.tx_oidc_login.defaultRedirectPid` UID of the page that users will be
  redirected to, if no `redirect_url` parameter is set with the request.

### PKCE (Proof of Key for Code Exchange)

If your IdP supports _Proof of Key for Code Exchange_ you can enable it
by setting `enableCodeVerifier` in the extension configuration. A shared secret
will be sent along preventing _Authorization Code Interception Attacks_. See
[RFC7636](https://tools.ietf.org/html/rfc7636) for details.

## Logging

For debugging purposes it may be helpful to enable DEBUG-level logging.
In addition, rerouting all log lines to a dedicated file is also recommended.

The following instructions in your sitepackage's `ext_localconf.php`
or global `config/system/additional.php` file will configure the system
appropriately:

```php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Causal']['Oidc']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'oidc'
        ],
    ],
];
```

**Hint:** Be sure to read
[Configuration of the Logging system](https://docs.typo3.org/permalink/t3coreapi:logging-configuration)
to fine-tune your configuration on any production website.

## Integrating your identity provider with specific packages

This extension uses an underlying PHP library for OAuth2, which can be extended for specific
identity providers by adding additional packages.

Example: For Microsoft EntraID (Azure) the package is [thenetworg/oauth2-azure](https://packagist.org/packages/thenetworg/oauth2-azure)

In order to use these kinds of packages, one needs to implement a custom
`OAuth2ProviderFactory`, which takes care of initializing the specific provider.

Here is an example for the aforementioned Azure package.

Register your custom factory class in the extension configuration `oauthProviderFactory = \Reelworx\Sitesetup\Authentication\OAuth2ProviderFactory`.
Put this class into your site-package, typically in the file `packages/<sitepackage>/Classes/Autentication/OAuth2ProviderFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Reelworx\Sitesetup\Authentication;

use Causal\Oidc\Factory\OAuthProviderFactoryInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/* requires some ENV variables to be set, see below */
final class OAuth2ProviderFactory implements OAuthProviderFactoryInterface
{
    public function create(array $settings): AbstractProvider
    {
        $options = [
            'clientId' => $settings['oidcClientKey'],
            'redirectUri' => $settings['oidcRedirectUri'],
            'urlAuthorize' => $settings['oidcEndpointAuthorize'],
            'urlAccessToken' => $settings['oidcEndpointToken'],
            'urlResourceOwnerDetails' => $settings['oidcEndpointUserInfo'],
            'scopes' => GeneralUtility::trimExplode(',', $settings['oidcClientScopes'], true),
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
            'tenant' => getenv('AZURE_OAUTH_CLIENT_TENANT'),
        ];
        if ($settings['oidcClientSecret']) {
            $options['clientSecret'] = $settings['oidcClientSecret'];
        } else {
            // https://learn.microsoft.com/en-us/entra/identity-platform/certificate-credentials
            // PEM certificate (newline potentially encoded as '\n'
            $options['clientCertificatePrivateKey'] = getenv('AZURE_OAUTH_CLIENT_CERTIFICATE');
            // SHA-1 thumbprint of the X.509 certificate's DER encoding.
            $options['clientCertificateThumbprint'] = getenv('AZURE_OAUTH_CLIENT_CERTIFICATE_THUMBPRINT');
        }
        return new Azure($options);
    }
}
```

## Developer information

For customization purposes this extension provides 8 events.
Please lookup their usage within the code to identify the right event(s) matching your requirements.

## Maintainer's corner

### Running acceptance tests

The `Build` folder contains a docker compose test environment for this oidc extension. It contains:
* TYPO3 v12 instance with ext-oidc installed
* TYPO3 v13 instance with ext-oidc installed
* mock oidc server
* Playwright test runner to run acceptance tests
* VNC Server to watch the playwright tests

To build the test environment and run the playwright tests run the following command:
```bash
cd Build
docker compose up --build --exit-code-from playwright && echo "Success" || echo "Fail"
```

## Reference

### Implementation concept

This extension builds a wrapper around the `league/oauth2-client` PHP library.
It provides TYPO3 [AuthenticationServices](https://docs.typo3.org/permalink/t3coreapi:authentication-service)
which integrate the authentication with frontend and backend.

In order to encapsulate and store the "state" parameter recommended for the request towards the IdP,
the extensions creates a JWT stored in a cookie. This allows for state-less authentication flow,
helpful for multi-server setups, where shared PHP-sessions are hard to implement.
(Note the distinction between PHP- and anonymous TYPO3-session here. During the authentication phase
there is no TYPO3-session available yet, so the "state" would have to be stored within a PHP-session.)

### Specification

https://openid.net/specs/openid-connect-core-1_0.html

## Credits

This TYPO3 extension is created and maintained by:
 - Xavier Perseguers (https://www.causal.ch/)
 - Markus Klein (https://reelworx.at/)

A big "Thanks" goes out to all contributors.
