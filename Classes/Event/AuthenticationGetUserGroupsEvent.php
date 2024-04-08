<?php

declare(strict_types=1);

namespace Causal\Oidc\Event;

/**
 * Customize user group mapping
 * if your resource owner structure differs form the default "Roles" workflow
 */
final class AuthenticationGetUserGroupsEvent
{
    protected string $groupTable;
    protected array $groups;
    protected array $resource;

    /**
     * @param string $groupTable - fe_groups or be_groups
     * @param array $groups - known user group ids
     * @param array $resourceOwner - resource owner data
     */
    public function __construct(string $groupTable, array $groups, array $resourceOwner)
    {
        $this->groupTable = $groupTable;
        $this->groups = $groups;
        $this->resource = $resourceOwner;
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

    /**
     * Set your customized user group ids
     */
    public function setUserGroups(array $groups): void
    {
        $this->groups = $groups;
    }
}