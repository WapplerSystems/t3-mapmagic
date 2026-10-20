<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Map Magic',
    'description' => 'TYPO3 Map Magic - Flexible TYPO3 map framework.',
    'category' => 'misc',
    'state' => 'stable',
    'author' => 'WapplerSystems',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => '',
    'version' => '14.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'frontend' => '14.3.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'address' => '',
            'filelist' => '14.3.0-14.99.99',
            'impexp' => '14.3.0-14.99.99',
            'lowlevel' => '14.3.0-14.99.99',
        ],
    ],
];
