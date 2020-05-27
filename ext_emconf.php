<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID Connect Authentication',
    'description' => 'This extension uses OpenID Connect to authenticate users.',
    'category' => 'services',
    'author' => 'Xavier Perseguers (Causal)',
    'author_company' => 'Causal Sàrl',
    'author_email' => 'xavier@causal.ch',
    'shy' => '',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'module' => '',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '0.4.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '7.1.0-7.2.99',
            'typo3' => '8.7.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Causal\\Oidc\\' => 'Classes']
    ],
];

