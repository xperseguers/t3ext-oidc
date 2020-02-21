<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

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
    'version' => '0.5.0',
    'constraints' => [
        'depends' => [
            'php' => '7.0.0-7.3.99',
            'typo3' => '8.7.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Causal\\Oidc\\' => 'Classes'],
    ],
];
