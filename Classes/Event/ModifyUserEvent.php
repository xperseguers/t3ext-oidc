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

final class ModifyUserEvent
{
    /**
     * @var array fe_users record
     */
    protected array $user;

    protected array $oidcResourceOwner;

    protected AbstractAuthenticationService $authenticationService;

    public function __construct(
        array $user,
        AbstractAuthenticationService $authenticationService,
        array $oidcResourceOwner
    ) {
        $this->user = $user;
        $this->authenticationService = $authenticationService;
        $this->oidcResourceOwner = $oidcResourceOwner;
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function getAuthenticationService(): AbstractAuthenticationService
    {
        return $this->authenticationService;
    }

    public function getOidcResourceOwner(): array
    {
        return $this->oidcResourceOwner;
    }
}
