<?php

namespace Causal\Oidc\Event;

/**
 * Customize user group mapping
 * if your resource owner structure differs form the default "Roles" workflow
 */
final class AuthenticationGetUserGroupsEvent
{
	/**
	 * @var array
	 */
	protected $groupTable;
	/**
	 * @var array
	 */
	protected $groups;
	/**
	 * @var array
	 */
	protected $resource;

	/**
	 * @param string $groupTable - fe_groups or be_groups
	 * @param array $groups - known user group ids
	 * @param array $resourceOwner - resource owner data
	 */
	public function __construct($groupTable, $groups, $resourceOwner)
	{
		$this->groupTable = (string)$groupTable;
		$this->groups = $groups;
		$this->resource = $resourceOwner;
	}

	/**
	 * @return string
	 */
	public function getGroupTable()
	{
		return $this->groupTable;
	}

	/**
	 * @return array
	 */
	public function getUserGroups()
	{
		return $this->groups;
	}

	/**
	 * @return array
	 */
	public function getResource()
	{
		return $this->resource;
	}

	/**
	 * Set your customized user group ids
	 * @param array $groups
	 */
	public function setUserGroups($groups): void
	{
		$this->groups = $groups;
	}
}
