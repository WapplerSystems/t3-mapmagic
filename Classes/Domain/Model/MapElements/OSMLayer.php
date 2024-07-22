<?php

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;


class OSMLayer extends Layer {


    public function getPublicConfiguration(): array
    {

        return [
            'label' => 'OpenStreetMap',
            'type' => 'background',
            'datasource' => [
                'type' => 'xyz',
                'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            ],
            'attribution' => '© OpenStreetMap contributors',
            'maxZoom' => 19,
        ];
    }



}
