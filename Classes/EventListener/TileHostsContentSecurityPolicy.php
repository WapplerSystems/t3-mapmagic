<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Event\PolicyMutatedEvent;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use WapplerSystems\MapMagic\Frontend\BasemapPresets;

/**
 * Map tiles are images from other hosts. Under a Content-Security-Policy
 * (always on in the v14 backend, optional per site) the browser blocks them
 * and maps stay grey. This allows the hosts of all basemap presets for
 * img-src. Tile servers entered by hand in a map are not known here; sites
 * using them add the host in their csp.yaml.
 */
#[AsEventListener('mapmagic/tile-hosts-csp')]
final readonly class TileHostsContentSecurityPolicy
{
    public function __construct(private BasemapPresets $basemapPresets) {}

    public function __invoke(PolicyMutatedEvent $event): void
    {
        $sources = [];
        foreach ($this->basemapPresets->all() as $preset) {
            $origin = $this->originOf((string)($preset['url'] ?? ''));
            if ($origin !== null) {
                $sources[$origin] = new UriValue($origin);
            }
        }
        if ($sources !== []) {
            $event->setCurrentPolicy($event->getCurrentPolicy()->extend(Directive::ImgSrc, ...array_values($sources)));
        }
    }

    /**
     * "https://{s}.tile.example.org/{z}/{x}/{y}.png" → "https://*.tile.example.org"
     */
    private function originOf(string $urlTemplate): ?string
    {
        $parts = parse_url(str_replace('{s}', 'subdomain-placeholder', $urlTemplate));
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $host = str_replace('subdomain-placeholder', '*', $parts['host']);
        return $parts['scheme'] . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
