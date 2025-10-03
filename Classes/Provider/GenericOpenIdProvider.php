<?php

declare(strict_types=1);

namespace Causal\Oidc\Provider;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use UnexpectedValueException;

class GenericOpenIdProvider extends GenericProvider
{
    /**
     * @param AccessToken $token
     * @return mixed
     * @throws UnexpectedValueException
     * @throws IdentityProviderException
     */
    protected function fetchResourceOwnerDetails(AccessToken $token)
    {
        if ($this->getResourceOwnerDetailsUrl($token)) {
            // Using the access token, we may look up details about the resource owner
            return parent::fetchResourceOwnerDetails($token);
        }

        // extract resource owner from ID token
        $jwt = $token->getToken();
        $jwtDecoded = base64_decode(str_replace(['_', '-'], ['/' - '+'], explode('.', $jwt)[1]));
        $resourceOwner = json_decode($jwtDecoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException('The provided JWT is invalid', 1759222069);
        }
        return $resourceOwner;
    }
}
