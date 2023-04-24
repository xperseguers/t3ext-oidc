<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID Connect Authentication',
    'description' => 'This extension uses OpenID Connect to authenticate users.',
    'category' => 'services',
    'author' => 'Xavier Perseguers',
    'author_company' => 'Causal Sàrl',
    'author_email' => 'xavier@causal.ch',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.3.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.2.99',
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Causal\\Oidc\\' => 'Classes']
    ],
];

