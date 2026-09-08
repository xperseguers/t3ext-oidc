<?php

declare(strict_types=1);

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

namespace Causal\Oidc\Event;

use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

final class AuthenticationGetUserEvent
{
    protected array|false $user;

    protected AbstractAuthenticationService $authenticationService;

    public function __construct(array|false $user, AbstractAuthenticationService $authenticationService)
    {
        $this->user = $user;
        $this->authenticationService = $authenticationService;
    }

    /**
     * Array with user if authentication was successful or false on failure.
     */
    public function getUser(): array|false
    {
        return $this->user;
    }

    public function setUser(array|false $user): void
    {
        $this->user = $user;
    }

    public function getAuthenticationService(): AbstractAuthenticationService
    {
        return $this->authenticationService;
    }
}
