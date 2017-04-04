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
     * @param mixed $id
     * @param array $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     * @return void
     */
    public function processDatamap_afterDatabaseOperations($operation, $table, $id, array $fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler $pObj)
    {
        if (!($table === 'fe_groups' && $operation === 'update')) {
            return;
        }

        if (isset($fieldArray['tx_oidc_pattern']) && empty($fieldArray['tx_oidc_pattern'])) {
            // Pattern has been cleared => disconnect group from users (see https://github.com/xperseguers/t3ext-oidc/issues/11)
            $database = $this->getDatabaseConnection();
            $usersInThisUserGroup = $database->exec_SELECTgetRows(
                'uid, usergroup',
                'fe_users',
                $database->listQuery('usergroup', $id, 'fe_users')
            );
            foreach ($usersInThisUserGroup as $user) {
                $userGroups = GeneralUtility::intExplode(',', $user['usergroup'], true);
                // Remove this user group from the list
                $index = array_search($id, $userGroups);
                unset($userGroups[$index]);
                $database->exec_UPDATEquery(
                    'fe_users',
                    'uid=' . (int)$user['uid'],
                    [
                        'usergroup' => implode(',', $userGroups),
                        'tstamp' => $GLOBALS['EXEC_TIME'],
                    ]
                );
            }
        }
    }

    /**
     * Returns the database connection.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
