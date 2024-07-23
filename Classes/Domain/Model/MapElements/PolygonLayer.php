<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;


class PolygonLayer extends Layer {


    public function getPublicConfiguration(): array
    {

        $latlngs = json_decode($this->properties['latlngs'],true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $latlngs = [];
        }

        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    $latlngs
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
