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
 * Named tile layers, so map files say `preset: osm` instead of repeating
 * URL and attribution, and a site can switch all maps to its own tile
 * server in one place.
 *
 * Note on the OpenStreetMap preset: tile.openstreetmap.org is a donated
 * service with a usage policy that forbids heavy use; production sites with
 * real traffic should point the preset at their own tile proxy via
 * $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['map_magic']['basemapPresets'].
 */
final class BasemapPresets
{
    private const DEFAULTS = [
        'osm' => [
            'type' => 'xyz',
            'id' => 'osm',
            'label' => 'OpenStreetMap',
            'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
            'maxZoom' => 19,
        ],
        'osm-de' => [
            'type' => 'xyz',
            'id' => 'osm-de',
            'label' => 'OpenStreetMap Deutschland',
            'url' => 'https://tile.openstreetmap.de/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
            'maxZoom' => 19,
        ],
        'carto-light' => [
            'type' => 'xyz',
            'id' => 'carto-light',
            'label' => 'Hell (CARTO)',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            'subdomains' => 'abcd',
            'attribution' => '&copy; OpenStreetMap-Mitwirkende &copy; <a href="https://carto.com/attributions">CARTO</a>',
            'maxZoom' => 20,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $custom = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['map_magic']['basemapPresets'] ?? [];
        return array_replace(self::DEFAULTS, is_array($custom) ? $custom : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        $preset = $this->all()[$name] ?? null;
        if (!is_array($preset)) {
            throw new \InvalidArgumentException(sprintf('Unknown basemap preset "%s"', $name), 1792460006);
        }
        return $preset;
    }
}
