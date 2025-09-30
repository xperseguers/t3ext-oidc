<?php

declare(strict_types=1);

/**
 * This file is part of the "oidc" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Causal\Oidc\Factory;

use Causal\Oidc\Provider\GenericOpenIdProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class GenericOAuthProviderFactory implements OAuthProviderFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $clientFactory,
        private RequestFactory $requestFactory,
    ) {}

    public function create(array $settings): AbstractProvider
    {
        $collaborators = [
            'httpClient' => $this->clientFactory->getClient(),
            'requestFactory' => $this->requestFactory,
        ];

        return new GenericOpenIdProvider(
            [
                'clientId' => $settings['oidcClientKey'],
                'clientSecret' => $settings['oidcClientSecret'],
                'redirectUri' => $settings['oidcRedirectUri'],
                'urlAuthorize' => $settings['oidcEndpointAuthorize'],
                'urlAccessToken' => $settings['oidcEndpointToken'],
                'urlResourceOwnerDetails' => $settings['oidcEndpointUserInfo'],
                'responseResourceOwnerId' => 'sub',
                'accessTokenResourceOwnerId' => 'sub',
                'scopes' => GeneralUtility::trimExplode(',', $settings['oidcClientScopes'], true),
            ],
            $collaborators
        );
    }
}
