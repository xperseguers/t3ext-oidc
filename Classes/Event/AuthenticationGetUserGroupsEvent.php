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

/**
 * Customize user group mapping
 * if your resource owner structure differs from the default "Roles" workflow
 */
final class AuthenticationGetUserGroupsEvent
{
    protected string $groupTable;
    protected array $groups;
    protected array $resource;
    protected AbstractAuthenticationService $authenticationService;

    /**
     * @param string $groupTable - fe_groups or be_groups
     * @param array $groups - known user group ids
     * @param array $resourceOwner - resource owner data
     */
    public function __construct(string $groupTable, array $groups, array $resourceOwner, AbstractAuthenticationService $authenticationService)
    {
        $this->groupTable = $groupTable;
        $this->groups = $groups;
        $this->resource = $resourceOwner;
        $this->authenticationService = $authenticationService;
    }

    public function getGroupTable(): string
    {
        return $this->groupTable;
    }

    public function getUserGroups(): array
    {
        return $this->groups;
    }

    public function getResource(): array
    {
        return $this->resource;
    }

    public function getAuthenticationService(): AbstractAuthenticationService
    {
        return $this->authenticationService;
    }

    /**
     * Set your customized user group ids
     */
    public function setUserGroups(array $groups): void
    {
        $this->groups = $groups;
    }
}
