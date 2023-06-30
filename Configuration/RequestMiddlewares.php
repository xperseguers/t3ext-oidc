<?php
return [
    'frontend' => [
        'oidccallback' => [
            'target' => \Causal\Oidc\Middleware\OauthCallback::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid'
            ]
        ],
    ],
];
