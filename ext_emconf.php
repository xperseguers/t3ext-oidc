<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID Connect Authentication',
    'description' => 'This extension uses OpenID Connect to authenticate users.',
    'category' => 'services',
    'author' => 'Xavier Perseguers, Markus Klein',
    'author_company' => 'Causal Sàrl, Reelworx GmbH',
    'author_email' => 'xavier@causal.ch',
    'state' => 'stable',
    'version' => '5.0.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.4.99',
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
