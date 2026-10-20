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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\Address\Domain\Model\Address;
use WapplerSystems\Address\Domain\Repository\AddressRepository;

/**
 * Address records of EXT:address as GeoJSON points.
 *
 *     source:
 *       type: addresses
 *       pids: [12, 13]       # storage folders, required
 *       categories: [4, 7]   # optional, address must have at least one of them
 *
 * Feature properties: uid, name, title, address, zip, city, country, email,
 * telephone, website, markerColor, categories (titles) and categoryUids.
 * Records without coordinates are skipped: a marker at 0,0 in the Gulf of
 * Guinea helps nobody.
 *
 * EXT:address is optional, so its classes are only touched inside resolve();
 * a constructor dependency would break the container when it is missing.
 */
#[AutoconfigureTag(SourceProviderInterface::TAG)]
final class AddressSourceProvider implements SourceProviderInterface
{
    public function supports(array $source): bool
    {
        return ($source['type'] ?? null) === 'addresses';
    }

    public function resolve(array $source, ServerRequestInterface $request): array
    {
        if (!class_exists(AddressRepository::class)) {
            throw new \RuntimeException('Source type "addresses" needs EXT:address', 1792460003);
        }
        $pids = array_values(array_filter(array_map(intval(...), (array)($source['pids'] ?? []))));
        if ($pids === []) {
            throw new \InvalidArgumentException('Source type "addresses" needs "pids"', 1792460004);
        }
        $categories = array_map(intval(...), (array)($source['categories'] ?? []));

        $features = [];
        /** @var Address $address */
        foreach (GeneralUtility::makeInstance(AddressRepository::class)->findAllInPids($pids) as $address) {
            $lat = $address->getLatitude();
            $lng = $address->getLongitude();
            if (!$lat || !$lng) {
                continue;
            }
            $categoryTitles = [];
            $categoryUids = [];
            foreach ($address->getCategories() as $category) {
                $categoryTitles[] = $category->getTitle();
                $categoryUids[] = (int)$category->getUid();
            }
            if ($categories !== [] && array_intersect($categories, $categoryUids) === []) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $address->getUid(),
                'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'properties' => [
                    'uid' => $address->getUid(),
                    // Companies keep their name in "title"; getName() is first + last name.
                    'name' => trim($address->getName()) !== '' ? trim($address->getName()) : $address->getTitle(),
                    'title' => $address->getTitle(),
                    'address' => $address->getAddress(),
                    'zip' => $address->getZip(),
                    'city' => $address->getCity(),
                    'country' => $address->getCountry(),
                    'email' => $address->getFirstEmailAddress(),
                    'telephone' => $address->getContactByType('telephone')?->getContent() ?? '',
                    'website' => $address->getContactByType('website')?->getContent() ?? '',
                    'markerColor' => $address->getMarkerColor(),
                    'categories' => $categoryTitles,
                    'categoryUids' => $categoryUids,
                ],
            ];
        }

        return ['type' => 'geojson', 'data' => ['type' => 'FeatureCollection', 'features' => $features]];
    }
}
