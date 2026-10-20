<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

class PolygonLayer extends Layer {


    public function getPublicConfiguration(): array
    {

        $lnglats = json_decode($this->properties['lnglats'],true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $lnglats = [];
        }

        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    $lnglats
                ]
            ],
        ];

        return [
            'label' => 'Polygon Layer',
            'type' => 'shapes',
            'datasource' => [
                'type' => 'geojson',
                'data' => $geojson
            ],
        ];
    }


}
