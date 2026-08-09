<?php

declare(strict_types=1);

namespace Causal\Oidc\Updates;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('OIDCPluginUpdater')]
class PluginUpdater implements UpgradeWizardInterface
{
    protected const TABLE_CONTENT = 'tt_content';
    protected const TABLE_BACKEND_USER_GROUPS = 'be_groups';

    protected const MIGRATION_SETTINGS = [
        'oidc_login' => 'oidc_login',
    ];

    public function __construct(protected ConnectionPool $connectionPool) {}

    public function getTitle(): string
    {
        return 'EXT:oidc Migrate plugin';
    }

    public function getDescription(): string
    {
        return 'List-Type plugins are migrated to CType. User permissions are migrated too.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }
    public function updateNecessary(): bool
    {
        return (
                $this->columnsExistInContentTable()
                && $this->getMigrationRecords()
            )
            || (
                $this->columnsExistInBackendUserGroupsTable()
                && $this->hasNoLegacyBackendGroupsExplicitAllowDenyConfiguration()
                && $this->hasBackendUserGroupsToUpdate()
            );
    }

    public function executeUpdate(): bool
    {
        if (
            $this->columnsExistInContentTable()
            && $this->getMigrationRecords()
        ) {
            $this->performMigration();
        }
        if (
            $this->columnsExistInBackendUserGroupsTable()
            && $this->hasNoLegacyBackendGroupsExplicitAllowDenyConfiguration()
            && $this->hasBackendUserGroupsToUpdate()
        ) {
            $this->updateBackendUserGroups();
        }

        return true;
    }

    public function performMigration(): void
    {
        $records = $this->getMigrationRecords();
        foreach ($records as $record) {
            $targetCtype = $this->getTargetCType($record['list_type']);
            if ($targetCtype === '') {
                continue;
            }

            $this->updateContentElement($record['uid'], $targetCtype);
        }
    }

    protected function getMigrationRecords(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONTENT);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid', 'pid', 'CType', 'list_type', 'pi_flexform')
            ->from(self::TABLE_CONTENT)
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter('list')
                ),
                $queryBuilder->expr()->in(
                    'list_type',
                    $queryBuilder->createNamedParameter(array_keys(static::MIGRATION_SETTINGS), ArrayParameterType::STRING)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function getTargetCType(string $sourceListType): string
    {
        // direct migration form Plugin to ContentElement
        if (!is_array(static::MIGRATION_SETTINGS[$sourceListType])) {
            return static::MIGRATION_SETTINGS[$sourceListType];
        }

        return '';
    }

    /**
     * Updates list_type of the given content element UID
     */
    protected function updateContentElement(int $uid, string $newCtype): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONTENT);
        $queryBuilder->update(self::TABLE_CONTENT)
            ->set('CType', $newCtype)
            ->set('list_type', '')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER))
            )
            ->executeStatement();
    }

    protected function hasBackendUserGroupsToUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_BACKEND_USER_GROUPS);
        $queryBuilder->getRestrictions()->removeAll();

        $searchConstraints = [];
        foreach (array_keys(static::MIGRATION_SETTINGS) as $listTyp) {
            $searchConstraints[] = $queryBuilder->expr()->like(
                'explicit_allowdeny',
                $queryBuilder->createNamedParameter(
                    '%' . $queryBuilder->escapeLikeWildcards('tt_content:list_type:' . $listTyp) . '%'
                )
            );
        }

        $queryBuilder
            ->count('uid')
            ->from(self::TABLE_BACKEND_USER_GROUPS)
            ->where(
                $queryBuilder->expr()->or(...$searchConstraints),
            );

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }

    protected function updateBackendUserGroups(): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_BACKEND_USER_GROUPS);

        /**
         * @var string $listType
         * @var string|string[] $contentTypeMigration
         */
        foreach (static::MIGRATION_SETTINGS as $listType => $contentTypeMigration) {
            if (is_array($contentTypeMigration)) {
                $contentTypes = array_column($contentTypeMigration, 'targetCType');
            } else {
                $contentTypes = [$contentTypeMigration];
            }

            foreach ($this->getBackendUserGroupsToUpdate($listType) as $record) {
                $fields = GeneralUtility::trimExplode(',', $record['explicit_allowdeny'], true);
                foreach ($fields as $key => $field) {
                    if ($field === 'tt_content:list_type:' . $listType) {
                        unset($fields[$key]);
                        foreach ($contentTypes as $contentType) {
                            $fields[] = 'tt_content:CType:' . $contentType;
                        }
                    }
                }

                $connection->update(
                    self::TABLE_BACKEND_USER_GROUPS,
                    [
                        'explicit_allowdeny' => implode(',', array_unique($fields)),
                    ],
                    ['uid' => (int)$record['uid']]
                );
            }
        }
    }

    protected function getBackendUserGroupsToUpdate(string $listType): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_BACKEND_USER_GROUPS);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('uid', 'explicit_allowdeny')
            ->from(self::TABLE_BACKEND_USER_GROUPS)
            ->where(
                $queryBuilder->expr()->like(
                    'explicit_allowdeny',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards('tt_content:list_type:' . $listType) . '%'
                    )
                ),
            );
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Returns true, if no legacy explicit_allowdeny be_groups configuration is found. Note, that we can not rely
     * BackendGroupsExplicitAllowDenyMigration status here, since the update must also be executed for new
     * TYPO3 v13+ installations, where BackendGroupsExplicitAllowDenyMigration is not required.
     */
    protected function hasNoLegacyBackendGroupsExplicitAllowDenyConfiguration(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_BACKEND_USER_GROUPS);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->count('uid')
            ->from(self::TABLE_BACKEND_USER_GROUPS)
            ->where(
                $queryBuilder->expr()->like(
                    'explicit_allowdeny',
                    $queryBuilder->createNamedParameter(
                        '%ALLOW%'
                    )
                ),
            );
        return (int)$queryBuilder->executeQuery()->fetchOne() === 0;
    }

    protected function columnsExistInBackendUserGroupsTable(): bool
    {
        $schemaManager = $this->connectionPool
            ->getConnectionForTable(self::TABLE_BACKEND_USER_GROUPS)
            ->createSchemaManager();

        return isset($schemaManager->listTableColumns(self::TABLE_BACKEND_USER_GROUPS)['explicit_allowdeny']);
    }

    protected function columnsExistInContentTable(): bool
    {
        $schemaManager = $this->connectionPool
            ->getConnectionForTable(self::TABLE_CONTENT)
            ->createSchemaManager();

        $tableColumnNames = array_flip(
            array_map(
                static fn(Column $column) => $column->getName(),
                $schemaManager->listTableColumns(self::TABLE_CONTENT)
            )
        );

        foreach (['CType', 'list_type'] as $column) {
            if (!isset($tableColumnNames[$column])) {
                return false;
            }
        }

        return true;
    }
}
