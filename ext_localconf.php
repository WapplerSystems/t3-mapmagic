<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\MapMagic\Controller\MapFrontendController;
use WapplerSystems\MapMagic\Hooks\MapElementHooks;
use WapplerSystems\MapMagic\Hooks\ImportExportHook;
use WapplerSystems\MapMagic\Mvc\Property\PropertyMappingConfiguration;

defined('TYPO3') or die();

call_user_func(static function () {
    if (ExtensionManagementUtility::isLoaded('impexp')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/impexp/class.tx_impexp.php']['before_addSysFileRecord'][1530637161]
            = ImportExportHook::class . '->beforeAddSysFileRecordOnImport';
    }

    // Add module configuration
    ExtensionManagementUtility::addTypoScriptSetup(
        'module.tx_mapmagic {
    settings {
        yamlConfigurations {
            10 = EXT:map_magic/Configuration/Yaml/MapSetup.yaml
        }
    }
}'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['afterSubmit'][1489772699]
        = MapElementHooks::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeRendering'][1489772699]
        = MapElementHooks::class;

    // FE file upload processing
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['afterBuildingFinished'][1489772699]
        = PropertyMappingConfiguration::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['afterFormStateInitialized'][1613296803]
        = PropertyMappingConfiguration::class;

    // Register "mapmagicvh:" namespace
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['mapmagicvh'][] = 'WapplerSystems\\MapMagic\\ViewHelpers';

    // Register FE plugin
    ExtensionUtility::configurePlugin(
        'MapMagic',
        'Map',
        [MapFrontendController::class => 'render, perform'],
        [MapFrontendController::class => 'perform'],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
});
