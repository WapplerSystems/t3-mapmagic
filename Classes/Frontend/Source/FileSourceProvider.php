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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * GeoJSON and GPX sources given as `url` (any reference ResourceUrlResolver
 * understands) or inline `data`. Inline data is passed through untouched;
 * the renderer validates it.
 */
#[AutoconfigureTag(SourceProviderInterface::TAG, ['priority' => -100])]
final readonly class FileSourceProvider implements SourceProviderInterface
{
    public function __construct(private ResourceUrlResolver $urlResolver) {}

    public function supports(array $source): bool
    {
        return in_array($source['type'] ?? null, ['geojson', 'gpx'], true);
    }

    public function resolve(array $source, ServerRequestInterface $request): array
    {
        if (array_key_exists('data', $source)) {
            return ['type' => $source['type'], 'data' => $source['data']];
        }
        return ['type' => $source['type'], 'url' => $this->urlResolver->resolve((string)($source['url'] ?? ''))];
    }
}
