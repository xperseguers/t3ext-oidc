<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID Connect Authentication',
    'description' => 'This extension uses OpenID Connect to authenticate users.',
    'category' => 'services',
    'author' => 'Xavier Perseguers, Markus Klein',
    'author_company' => 'Causal Sàrl, Reelworx GmbH',
    'author_email' => 'xavier@causal.ch',
    'state' => 'stable',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.99-8.4.99',
            'typo3' => '11.5.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
