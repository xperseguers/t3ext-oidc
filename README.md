# OpenID Connect

This extension lets you authenticate Frontend users against an OpenID Connect server. It is preconfigured to work with
the [WSO2 Identity Server](https://wso2.com/identity-and-access-management/) from the Swiss Alpine Club but may be used
with your own identity server as well.

If you are a Swiss Alpine Club section, be sure to get in touch with Bern in order to get your dedicated private key and
secret.


## Default FE Loginbox

This extension integrates with the system extension 'felogin' and provides a new marker `###OPENID_CONNECT###` to be
used in the felogin template. A sample template is included. The marker will be replaced by a login link, pointing to
the  authorization endpoint of the authorization server.

## OIDC Login

If openid_connect is your only means of frontend login, you can use the included "OIDC Login" plugin. Add it to your 
login page, where you would normally add the felogin box. After adding the OIDC Login plugin, requests to the login 
page will immediately be redirected to the authorization server.

After the login process, the user will be redirected:

- The OIDC Login supports the same redirect_url parameter as the felogin box
- If no parameter is set, OIDC Login will redirect the user to the page configured at
  `plugin.tx_oidc_login.defaultRedirectPid`.
- If that configuration is not set either, the user will be redirected to '/'.
 

## Configuring

### Mapping Frontend User Fields

- Configuration is done through TypoScript within `plugin.tx_oidc.mapping.fe_users`
- OIDC attributes will be recognized by the specific characters `<>`:

  ```
  email = <mail>
  ```

- You may combine multiple markers as well, e.g.,

  ```
  name = <family_name>, <given_name>
  ```

- Support for [stdWrap](https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Functions/Stdwrap.html) in field
  definition, e.g.,

  ```
  name = <name>
  name.wrap = |-OIDC
  ```

- Support for [TypoScript "split"](https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Functions/Stdwrap.html#data)
  (`//`). This will check multiple field names and return the first one yielding some non-empty value. E.g.,

  ```
  username = <sub> // <contact_number> // <emailaddress> // <benutzername>
  ```

### Mapping Frontend User Groups

- Create your groups within TYPO3
- Use the additional pattern to relate it to roles within OpenID Connect
- Local TYPO3 groups (not related to some role) will be kept upon authenticating
- Default TYPO3 group(s) as configured in Extension Manager will always be added

### OIDC Login

- `plugin.tx_oidc_login.defaultRedirectPid` UID of the page that users will be redirected to, if no `redirect_url` 
parameter is set. 

## Logging

This extension makes use of the Logging system introduced in TYPO3 CMS 6.0. It is far more flexible than the old one
writing to the "sys_log" table. Technical details may be found in the
[TYPO3 Core API](https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Logging/Index.html#logging).

As an administrator, what you should know is that the TYPO3 Logger forwards log records to "Writers", which persist the
log record.

By default, with a vanilla TYPO3 installation, messages are written to the default log file
(`typo3temp/logs/typo3_*.log`).


### Dedicated Log File for OpenID Connect

If you want to redirect every logging information from this extension to `typo3temp/logs/oidc.log` and send log
entries with level "WARNING" or above to the system log, you may add following configuration to
`typo3conf/AdditionalConfiguration.php`:

```
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Causal']['Oidc']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFile' => 'typo3temp/logs/oidc.log'
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
