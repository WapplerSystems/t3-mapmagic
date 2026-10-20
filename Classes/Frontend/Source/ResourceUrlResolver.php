<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend\Source;

use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Converts the ways an editor can point at a file into a URL the browser can
 * fetch. Shared by data sources and image basemaps.
 *
 * Accepted:
 * - `https://…`, `http://…`, `//…`, `/…`  unchanged
 * - `EXT:ext/Resources/Public/…`           public resource of an extension
 * - `t3://file?uid=12`                      FAL file
 * - `1:/maps/plan.svg`                      FAL combined identifier
 * - `fileadmin/maps/plan.svg`               path relative to the web root
 */
final readonly class ResourceUrlResolver
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private LinkService $linkService,
    ) {}

    public function resolve(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new \InvalidArgumentException('Empty file reference', 1792460001);
        }
        if (preg_match('#^(https?:)?//#i', $reference) === 1 || str_starts_with($reference, '/')) {
            return $reference;
        }
        if (PathUtility::isExtensionPath($reference)) {
            return PathUtility::getPublicResourceWebPath($reference);
        }
        if (str_starts_with($reference, 't3://')) {
            $file = $this->linkService->resolve($reference)['file'] ?? null;
            return $this->publicUrl($file, $reference);
        }
        if (preg_match('#^\d+:/#', $reference) === 1) {
            return $this->publicUrl($this->resourceFactory->retrieveFileOrFolderObject($reference), $reference);
        }
        return '/' . ltrim($reference, '/');
    }

    private function publicUrl(mixed $file, string $reference): string
    {
        $url = $file instanceof File ? $file->getPublicUrl() : null;
        if ($url === null || $url === '') {
            throw new \InvalidArgumentException(sprintf('File "%s" is not publicly accessible', $reference), 1792460002);
        }
        return $url;
    }
}
