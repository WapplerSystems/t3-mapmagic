<?php

use WapplerSystems\MapMagic\Controller\Backend\MapAjaxController;

// All routes are reachable only for users with access to the MapMagic module.
$route = static fn(string $path, string $method): array => [
    'path' => '/mapmagic/' . $path,
    'methods' => ['POST'],
    'target' => MapAjaxController::class . '::' . $method,
    'inheritAccessFromModule' => 'content_mapmagic',
];

return [
    'mapmagic_preview' => $route('preview', 'previewAction'),
    'mapmagic_save' => $route('save', 'saveAction'),
    'mapmagic_yaml_dump' => $route('yaml/dump', 'yamlDumpAction'),
    'mapmagic_yaml_parse' => $route('yaml/parse', 'yamlParseAction'),
    'mapmagic_create' => $route('create', 'createAction'),
    'mapmagic_duplicate' => $route('duplicate', 'duplicateAction'),
    'mapmagic_delete' => $route('delete', 'deleteAction'),
];
