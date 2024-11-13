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

namespace Causal\Oidc\Hooks;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks for \TYPO3\CMS\Core\DataHandling\DataHandler.
 */
class DataHandlerOidc
{
    public function processDatamap_afterDatabaseOperations(string $operation, string $table, int|string $id, array $fieldArray): void
    {
        if ($table !== 'fe_groups' || $operation !== 'update') {
            return;
        }

        if (isset($fieldArray['tx_oidc_pattern']) && empty($fieldArray['tx_oidc_pattern'])) {
            // Pattern has been cleared => disconnect group from users (see https://github.com/xperseguers/t3ext-oidc/issues/11)
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('fe_users');
            $queryBuilder->getRestrictions()->removeAll();
            $usersInThisUserGroup = $queryBuilder
                ->select('uid', 'usergroup')
                ->from('fe_users')
                ->where(
                    $queryBuilder->expr()->inSet('usergroup', (string)$id)
                )
                ->executeQuery();

            $tableConnection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('fe_users');
            while ($user = $usersInThisUserGroup->fetchAssociative()) {
                $userGroups = GeneralUtility::intExplode(',', $user['usergroup'], true);
                // Remove this user group from the list
                $index = array_search($id, $userGroups);
                unset($userGroups[$index]);

                $tableConnection->update(
                    'fe_users',
                    [
                        'usergroup' => implode(',', $userGroups),
                        'tstamp' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
                    ],
                    [
                        'uid' => $user['uid'],
                    ]
                );
            }
        }
    }
}
