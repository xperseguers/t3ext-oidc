# OpenID Connect

TODO: add some description


## Configuring

TODO: add some description


## Logging

This extension makes use of the Logging system introduced in TYPO3 CMS 6.0. It is far more flexible than the old one
writing to the "sys_log" table. Technical details may be found in the
[TYPO3 Core API](https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Logging/Index.html#logging).

As an administrator, what you should know is that the TYPO3 Logger forwards log records to "Writers", which persist the
log record.

By default, with a vanilla TYPO3 installation, messages are written to the default log file
(`typo3temp/logs/typo3_*.log`).


### Dedicated log file for OpenID Connect

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
[Configuration of the Logging system](https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Logging/Configuration/Index.html#logging-configuration)
to fine-tune your configuration on any production website.
