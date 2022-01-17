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

namespace Causal\Oidc\Service;

use League\OAuth2\Client\Token\AccessToken;

/**
 * Interface for hooks to review the resource owner data before it is used to convert it to a user.
 */
interface ReviewResourceOwnerHookInterface
{

    /**
     * Review the resource owner data before user conversion takes place.
     * MUST return the reviewed $resourceOwner or null.
     * Returning null denies access to the site.
     *
     * @param OAuthService $service
     * @param AccessToken $accessToken
     * @param array $resourceOwner
     * @return array|null
     */
    public function reviewResourceOwner(OAuthService $service, AccessToken $accessToken, array $resourceOwner): ?array;

}
