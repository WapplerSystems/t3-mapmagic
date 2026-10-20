<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend;

/**
 * Page cache tag of a map definition file. The plugin is cached, so pages
 * showing a map carry this tag and saving the map flushes exactly them.
 */
final class MapCacheTag
{
    public static function for(string $persistenceIdentifier): string
    {
        return 'mapmagic_' . md5($persistenceIdentifier);
    }
}
