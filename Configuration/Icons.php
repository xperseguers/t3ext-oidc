<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-oidc-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:oidc/Resources/Public/Icons/Extension.svg',
    ],
];
