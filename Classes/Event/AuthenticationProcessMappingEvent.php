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

use Psr\Http\Message\RequestInterface;

final class AuthenticationProcessMappingEvent
{
    public function __construct(
        public readonly RequestInterface $request,
        public readonly string $databaseTable,
        public readonly array $existingUser,
        public readonly array $resourceOwner,
        public array $mappedData,
    ) {}
}
