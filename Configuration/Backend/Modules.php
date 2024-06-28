<?php

use WapplerSystems\MapMagic\Controller\MapEditorController;
use WapplerSystems\MapMagic\Controller\MapManagerController;

/**
 * Definitions for modules provided by EXT:map_magic
 */
return [
    'web_FormFormbuilder' => [
        'parent' => 'web',
        'access' => 'user',
        'iconIdentifier' => 'module-form',
        'inheritNavigationComponentFromMainModule' => false,
        'labels' => 'LLL:EXT:map_magic/Resources/Private/Language/locallang_module.xlf',
        'extensionName' => 'MapMagic',
        'controllerActions' => [
            MapManagerController::class => [
                'index', 'show', 'create', 'duplicate', 'references', 'delete',
            ],
            MapEditorController::class => [
                'index', 'saveForm', 'renderFormPage', 'renderRenderableOptions',
            ],
        ],
    ],
];
