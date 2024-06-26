<?php

$EM_CONF['map_magic'] = [
    'title' => 'Map Magic',
    'description' => 'Leaflet / OpenStreetMap for address',
    'category' => 'plugin',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3@wappler.systems',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '12.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'address' => '12.0.0'
        ],
        'conflicts' => [],
        'suggests' => [],
    ]
];
