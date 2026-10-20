<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend\Source;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns a `source` of a map definition file into a source the renderer
 * understands: `{type: geojson|gpx, url}` or `{type: geojson|gpx, data}`.
 *
 * The file format may use TYPO3-specific sources (FAL paths, records); the
 * renderer never sees them. Providers are asked in order of their priority
 * tag, the first one whose supports() returns true wins. Implementations
 * tag themselves with #[AutoconfigureTag(SourceProviderInterface::TAG)].
 */
interface SourceProviderInterface
{
    public const TAG = 'mapmagic.source_provider';

    public function supports(array $source): bool;

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed> renderer source
     */
    public function resolve(array $source, ServerRequestInterface $request): array;
}
