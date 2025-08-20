<?php
return [
    'frontend' => [
        'causal/oidc/callback' => [
            'target' => \Causal\Oidc\Middleware\OauthCallback::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid',
            ],
        ],
        'causal/oidc/auth-url' => [
            'target' => \Causal\Oidc\Middleware\AuthenticationUrlRequest::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
    'backend' => [
        'causal/oidc/callback' => [
            'target' => \Causal\Oidc\Middleware\OauthCallback::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
