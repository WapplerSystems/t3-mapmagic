<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use WapplerSystems\MapMagic\Frontend\Source\ResourceUrlResolver;
use WapplerSystems\MapMagic\Frontend\Source\SourceResolver;

/**
 * Turns a map definition file into the JSON the renderer reads.
 *
 * The file uses the renderer format (see MapMagicJs/src/schema.ts) plus a
 * few TYPO3 conveniences that are resolved here, so the renderer never has
 * to know about TYPO3:
 *
 * - file header keys (`type: MapMagic`, `identifier`, …) are dropped,
 * - `version` and a page-unique `id` are set,
 * - basemaps may be given as `preset: osm` (see BasemapPresets),
 * - URLs of image basemaps, sources and image icons may be EXT:/FAL references,
 * - sources may be TYPO3-specific (e.g. `type: addresses`).
 *
 * A layer whose source cannot be resolved is dropped and logged instead of
 * failing the whole map, mirroring what the renderer does for broken URLs.
 */
final readonly class MapDefinitionCompiler
{
    public const SCHEMA_VERSION = 1;

    /** Keys owned by the file / persistence layer, never sent to the browser. */
    private const FILE_KEYS = ['type', 'identifier', 'prototypeName', 'persistenceIdentifier', 'invalid', 'renderingOptions'];

    public function __construct(
        private SourceResolver $sourceResolver,
        private ResourceUrlResolver $urlResolver,
        private BasemapPresets $basemapPresets,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $definition parsed map definition file
     * @return array<string, mixed> renderer map definition
     */
    public function compile(array $definition, string $id, ServerRequestInterface $request): array
    {
        return $this->compileWithReport($definition, $id, $request)['map'];
    }

    /**
     * Same as compile(), plus the reasons why layers were dropped, for the
     * editor preview.
     *
     * @param array<string, mixed> $definition
     * @return array{map: array<string, mixed>, warnings: list<array{layer: string, message: string}>}
     */
    public function compileWithReport(array $definition, string $id, ServerRequestInterface $request): array
    {
        $warnings = [];
        $map = array_diff_key($definition, array_flip(self::FILE_KEYS));
        $map['version'] = self::SCHEMA_VERSION;
        $map['id'] = $id;

        $map['basemaps'] = array_map(
            fn(array $basemap): array => $this->compileBasemap($basemap),
            array_values(array_filter((array)($map['basemaps'] ?? []), is_array(...)))
        );

        $layers = [];
        foreach ((array)($map['layers'] ?? []) as $layer) {
            if (!is_array($layer)) {
                continue;
            }
            try {
                $layer['source'] = $this->sourceResolver->resolve((array)($layer['source'] ?? []), $request);
                $layers[] = $this->compileIcons($layer);
            } catch (\Throwable $e) {
                $warnings[] = ['layer' => (string)($layer['id'] ?? '?'), 'message' => $e->getMessage()];
                $this->logger->warning('Map "{map}": layer "{layer}" dropped: {message}', [
                    'map' => $definition['identifier'] ?? $id,
                    'layer' => $layer['id'] ?? '?',
                    'message' => $e->getMessage(),
                ]);
            }
        }
        $map['layers'] = $layers;

        return ['map' => $map, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $basemap
     * @return array<string, mixed>
     */
    private function compileBasemap(array $basemap): array
    {
        if (isset($basemap['preset'])) {
            $preset = $this->basemapPresets->get((string)$basemap['preset']);
            unset($basemap['preset']);
            // Fields given next to the preset (e.g. a translated label) win.
            $basemap = array_replace($preset, $basemap);
        }
        if (($basemap['type'] ?? null) === 'image' && isset($basemap['url'])) {
            $basemap['url'] = $this->urlResolver->resolve((string)$basemap['url']);
        }
        return $basemap;
    }

    /**
     * Resolves the URLs of image icons wherever a layer can carry one
     * (markers: icon, iconBy; route: startIcon, endIcon, waypoints).
     *
     * @param array<string, mixed> $layer
     * @return array<string, mixed>
     */
    private function compileIcons(array $layer): array
    {
        foreach (['icon', 'startIcon', 'endIcon'] as $key) {
            if (is_array($layer[$key] ?? null)) {
                $layer[$key] = $this->compileIcon($layer[$key]);
            }
        }
        if (is_array($layer['iconBy']['values'] ?? null)) {
            foreach ($layer['iconBy']['values'] as $value => $icon) {
                if (is_array($icon)) {
                    $layer['iconBy']['values'][$value] = $this->compileIcon($icon);
                }
            }
        }
        if (is_array($layer['waypoints']['icon'] ?? null)) {
            $layer['waypoints']['icon'] = $this->compileIcon($layer['waypoints']['icon']);
        }
        return $layer;
    }

    /**
     * @param array<string, mixed> $icon
     * @return array<string, mixed>
     */
    private function compileIcon(array $icon): array
    {
        if (($icon['type'] ?? null) === 'image' && isset($icon['url'])) {
            $icon['url'] = $this->urlResolver->resolve((string)$icon['url']);
        }
        return $icon;
    }
}
