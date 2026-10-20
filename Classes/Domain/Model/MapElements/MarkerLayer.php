<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class MarkerLayer extends Layer {


    public function getPublicConfiguration(): array
    {
        $uri = GeneralUtility::locationHeaderUrl(PathUtility::getPublicResourceWebPath('EXT:map_magic/Resources/Public/Images/marker-icon.png'));

        $lnglat = json_decode($this->properties['lnglat'],true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $lnglat = [];
        }

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            $lnglat
                        ]
                    ],
                ]
            ],
        ];

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
