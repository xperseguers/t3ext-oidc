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

final class ModifyResourceOwnerEvent
{
    /**
     * @var array resource owner data
     */
    protected array $resourceOwner;

    public function __construct(array $resourceOwner)
    {
        $this->resourceOwner = $resourceOwner;
    }

    public function getResourceOwner(): array
    {
        return $this->resourceOwner;
    }

    public function setResourceOwner(array $resourceOwner): void
    {
        $this->resourceOwner = $resourceOwner;
    }
}
