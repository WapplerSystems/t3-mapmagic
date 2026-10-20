<?php

use WapplerSystems\MapMagic\Controller\Backend\MapModuleController;

return [
    'content_mapmagic' => [
        'parent' => 'content',
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/content/mapmagic',
        'iconIdentifier' => 'mapmagic-module',
        'labels' => 'map_magic.module',
        // Maps are not tied to pages; the page tree would only take space from the editor.
        'inheritNavigationComponentFromMainModule' => false,
        // The old Extbase module of the EXT:form-based prototype.
        'aliases' => ['web_MapMagicbuilder'],
        'routes' => [
            '_default' => [
                'target' => MapModuleController::class . '::indexAction',
            ],
            'edit' => [
                'target' => MapModuleController::class . '::editAction',
            ],
        ],
    ],
];
