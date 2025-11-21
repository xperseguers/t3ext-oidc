# OpenID Connect for TYPO3 frontend login

This extension lets you authenticate frontend users against an OpenID Connect
provider.

Examples of such identity provider software or services are:

- Microsoft EntraID
- Google
- GitHub
- ID Austria
- WSO2 Identity Server
- Keycloak
- Authentik

## Breaking Change

With **Version 5.x** the default scope separator is changed from comma (`,`) to the space-character (` `)
to follow official [RFC-6749](https://datatracker.ietf.org/doc/html/rfc6749#section-3.3).

If your OpenID Server required a comma-separated list of scopes you have to change extension configuration `oidcClientScopeSeparator`

```
oidcClientScopeSeparator = ,
```

## Direct OIDC Login

If OpenID Connect is your only means of frontend login, you can use the included
"OIDC Login" plugin. Add it to your login page, where you would normally add the
felogin box. After adding the OIDC Login plugin, requests to the login page will
immediately be redirected to the identity provider.

After the login process, the user will be redirected:

- The OIDC Login supports the same `redirect_url` parameter as the felogin box
- If no parameter is set, OIDC Login will redirect the user to the page
  configured at `plugin.tx_oidc_login.defaultRedirectPid`.
- If that configuration is not set either, the user will be redirected to '/'.

## PKCE (Proof of Key for Code Exchange)

If your OIDC Login supports _Proof of Key for Code Exchange_ you can enable it
by checking `enableCodeVerifier` in the extension configuration. A shared secret
will be sent along preventing _Authorization Code Interception Attacks_. See
https://tools.ietf.org/html/rfc7636 for details.

## Configuration

### Mapping Frontend User Fields

- Configuration is done through TypoScript within
  `plugin.tx_oidc.mapping.fe_users`
- OIDC attributes will be recognized by the specific characters `<>`:

  ```
  email = <mail>
  ```

- You may combine multiple markers as well, e.g.,

  ```
  name = <family_name>, <given_name>
  ```

- Support for [stdWrap](https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Functions/Stdwrap.html) in
  field definition, e.g.,

  ```
  name = <name>
  name.wrap = |-OIDC
  ```

- Support for [TypoScript "split"](https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Functions/Stdwrap.html#data)
  (`//`). This will check multiple field names and return the first one yielding
  some non-empty value. E.g.,

  ```
  username = <sub> // <contact_number> // <emailaddress> // <benutzername>
  ```

### Mapping Frontend User Groups

- Create your groups within TYPO3
- Use the additional pattern to relate it to roles within OpenID Connect
- Local TYPO3 groups (not related to some role) will be kept upon authenticating
- Default TYPO3 group(s) as configured in Extension Manager will always be added

### OIDC Login

- `plugin.tx_oidc_login.defaultRedirectPid` UID of the page that users will be
  redirected to, if no `redirect_url` parameter is set.

## Logging

This extension makes use of the Logging system introduced in TYPO3 CMS 6.0. It
is far more flexible than the old one writing to the "sys_log" table. Technical
details may be found in the [TYPO3 Core API](https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Logging/Index.html#logging).

As an administrator, what you should know is that the TYPO3 Logger forwards log
records to "Writers", which persist the log record.

By default, with a vanilla TYPO3 installation, messages are written to the
default log file (`var/log/typo3_*.log`).


### Dedicated Log File for OpenID Connect

If you want to redirect every logging information from this extension to
`var/log/oidc.log` and send log entries with level "WARNING" or above to the
system log, you may add following configuration to
`typo3conf/AdditionalConfiguration.php`:

```
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Causal']['Oidc']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'oidc'
        ],
    ],

    // Configuration for WARNING severity, including all
    // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
    \TYPO3\CMS\Core\Log\LogLevel::WARNING => [
        \TYPO3\CMS\Core\Log\Writer\SyslogWriter::class => [],
    ],
];
```

**Hint:** Be sure to read
[Configuration of the Logging system](https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Logging/Configuration/Index.html#logging-configuration)
to fine-tune your configuration on any production website.


## Using additional identity provider packages

The underlying PHP library for OAuth2 can be extended for specific
identity providers by adding additional packages.

Example: For Microsoft EntraID (Azure) the package is [thenetworg/oauth2-azure](https://packagist.org/packages/thenetworg/oauth2-azure)

In order to use these kinds of packages, one needs to implement a custom
`OAuth2ProviderFactory`, which takes care of initializing the specific provider.

Here is an example for the aforementioned Azure package:

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

## Run acceptance tests
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

## Credits

This TYPO3 extension is created and maintained by:
 - Xavier Perseguers (https://www.causal.ch/)
 - Markus Klein (https://reelworx.at/)

A big "Thanks" goes out to all contributors.

