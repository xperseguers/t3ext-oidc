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

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class AuthenticationFetchUserEvent
{
    protected array $resourceInfo;

    /**
     * @var array<string|CompositeExpression>
     */
    protected array $conditions;

    protected QueryBuilder $queryBuilder;

    protected AbstractAuthenticationService $authenticationService;

    public function __construct(
        array $resourceInfo,
        array $conditions,
        QueryBuilder $queryBuilder,
        AbstractAuthenticationService $authenticationService
    ) {
        $this->resourceInfo = $resourceInfo;
        $this->conditions = $conditions;
        $this->queryBuilder = $queryBuilder;
        $this->authenticationService = $authenticationService;
    }

    public function getResourceInfo(): array
    {
        return $this->resourceInfo;
    }

    /**
     * @return array<CompositeExpression|string>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getAuthenticationService(): AbstractAuthenticationService
    {
        return $this->authenticationService;
    }
}
