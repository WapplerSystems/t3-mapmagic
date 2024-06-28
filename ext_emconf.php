<?php

$EM_CONF['map_magic'] = [
    'title' => 'Map Magic',
    'description' => 'TYPO3 Map Magic - Flexible TYPO3 map framework.',
    'category' => 'misc',
    'state' => 'stable',
    'author' => 'WapplerSystems',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => '',
    'version' => '12.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.17',
            'frontend' => '12.4.17',
        ],
        'conflicts' => [],
        'suggests' => [
            'filelist' => '12.4.17',
            'impexp' => '12.4.17',
            'lowlevel' => '12.4.17',
        ],
    ],
];
