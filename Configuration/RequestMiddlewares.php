<?php
return [
    'frontend' => [
        'oidccallback' => [
            'target' => \Causal\Oidc\Middleware\OauthCallback::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid',
            ],
        ],
        'oidcauthurl' => [
            'target' => \Causal\Oidc\Middleware\AuthenticationUrlRequest::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
