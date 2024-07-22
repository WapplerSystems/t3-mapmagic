<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\Address\Domain\Repository\AddressRepository;

class AddressDataLayer extends Layer
{


    public function initializeMapElement()
    {

        $addressRepository = GeneralUtility::makeInstance(AddressRepository::class);

        $addresses = $addressRepository->findAll();
        //DebugUtility::debug($addresses);

        $addressesOutput = [];

        foreach ($addresses as $address) {
            $addressesOutput[] = [
                'address' => $address->getAddress() . ', ' . $address->getZip() . ' ' . $address->getCity(),
                'lat' => $address->getLatitude(),
                'lng' => $address->getLongitude()
            ];
        }

        $this->properties['addresses'] = $addressesOutput;

        parent::initializeMapElement();
    }


    public function getPublicConfiguration(): array
    {

        $uri = GeneralUtility::locationHeaderUrl(PathUtility::getPublicResourceWebPath('EXT:map_magic/Resources/Public/Images/marker-icon.png'));

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [],
        ];

        foreach ($this->properties['addresses'] as $address) {
            $geojson['features'][] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        $address['lng'],
                        $address['lat']
                    ]
                ],
                'properties' => [
                    'address' => $address['address']
                ]
            ];
        }

        return [
            'label' => 'Address Data Layer',
            'type' => 'markers',
            'datasource' => [
                'type' => 'geojson',
                'data' => $geojson
            ],
            'style' => [
                'markerIconUrl' => $uri,
            ]
        ];

    }


}
