<?php

use WapplerSystems\MapMagic\Controller\FormEditorController;
use WapplerSystems\MapMagic\Controller\FormManagerController;

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
            FormManagerController::class => [
                'index', 'show', 'create', 'duplicate', 'references', 'delete',
            ],
            FormEditorController::class => [
                'index', 'saveForm', 'renderFormPage', 'renderRenderableOptions',
            ],
        ],
    ],
];
