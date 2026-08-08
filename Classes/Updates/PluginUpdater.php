<?php

declare(strict_types=1);

namespace Causal\Oidc\Updates;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('OIDCPluginUpdater')]
class PluginUpdater implements UpgradeWizardInterface
{
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
        $plugins = count($this->getMigrationRecords());
        $begroups = $this->hasBackendUserGroupsToUpdate();

        $description = 'List-Type plugins are migrated to CType. User permissions are migrated too.';

        if ($plugins) {
            $description .= 'This update wizard migrates all existing plugin settings and changes the plugin';
            $description .= 'to use the new plugins available. Count of plugins: ' . $plugins . ' ';
        }
        if ($begroups) {
            $description .= 'BE permissions will be migrated.';
        }
        return $description;
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->getMigrationRecords() || $this->hasBackendUserGroupsToUpdate();
    }

    public function executeUpdate(): bool
    {
        $this->performMigration();
        $this->updateBackendUserGroups();
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid', 'pid', 'CType', 'list_type', 'pi_flexform')
            ->from('tt_content')
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->update('tt_content')
            ->set('CType', $newCtype)
            ->set('list_type', '')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER))
            )
            ->executeStatement();
    }

    protected function hasBackendUserGroupsToUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
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
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->or(...$searchConstraints),
            );

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }

    protected function updateBackendUserGroups(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('be_groups');

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
                    'be_groups',
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('uid', 'explicit_allowdeny')
            ->from('be_groups')
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
}
