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
 * Interface for hooks related to the resource owner.
 */
interface ResourceOwnerHookInterface
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

    /**
     * Post-process for the user record (which is already persisted to the database).
     * The full record will automatically get reloaded from database after the hook
     * have been invoked.
     *
     * @param string $context The TYPO3 context (either 'BE' or 'FE')
     * @param array $user TYPO3 user record
     * @param array $data OpenID Connect data
     * @return void
     */
    public function postProcessUser($context, array $user, array $data);

}
