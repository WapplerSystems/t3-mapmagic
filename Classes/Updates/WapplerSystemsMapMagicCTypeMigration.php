<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Updates;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate;

/**
 * v12 installations stored the map plugin as CType "list" with list_type
 * "mapmagic_map". v14 has no list_type any more; without this wizard those
 * elements silently disappear from the frontend. It only works while the
 * list_type column still exists, so run it before the database analyzer
 * drops the column.
 */
#[UpgradeWizard('wapplersystemsMapMagicCTypeMigration')]
final class WapplerSystemsMapMagicCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Migrate "MapMagic" plugins to content elements';
    }

    public function getDescription(): string
    {
        return 'The MapMagic plugin is now registered as content element. Migrates existing records and backend user permissions.';
    }

    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'mapmagic_map' => 'mapmagic_map',
        ];
    }
}
