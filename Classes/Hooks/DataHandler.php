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

namespace Causal\Oidc\Hooks;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks for \TYPO3\CMS\Core\DataHandling\DataHandler.
 */
class DataHandler
{
    /**
     * Hooks into \TYPO3\CMS\Core\DataHandling\DataHandler after records have been saved to the database.
     *
     * @param string $operation
     * @param string $table
     * @param mixed  $id
     *
     * @return void
     */
    public function processDatamap_afterDatabaseOperations($operation, $table, $id, array $fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler $pObj)
    {
        if (!('fe_groups' === $table && 'update' === $operation)) {
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
                    $queryBuilder->expr()->inSet('usergroup', (string) $id)
                )
                ->execute()
                ->fetchAll();

            $tableConnection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('fe_users');
            foreach ($usersInThisUserGroup as $user) {
                $userGroups = GeneralUtility::intExplode(',', $user['usergroup'], true);
                // Remove this user group from the list
                $index = array_search($id, $userGroups);
                unset($userGroups[$index]);

                $tableConnection->update(
                    'fe_users',
                    [
                        'usergroup' => implode(',', $userGroups),
                        'tstamp' => $GLOBALS['EXEC_TIME'],
                    ],
                    [
                        'uid' => $user['uid'],
                    ]
                );
            }
        }
    }
}
