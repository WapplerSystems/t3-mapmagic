<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\MapMagic\Controller\FormFrontendController;
use WapplerSystems\MapMagic\Hooks\FormElementHooks;
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
            10 = EXT:map_magic/Configuration/Yaml/FormSetup.yaml
        }
    }
}'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterSubmit'][1489772699]
        = FormElementHooks::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1489772699]
        = FormElementHooks::class;

    // FE file upload processing
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'][1489772699]
        = PropertyMappingConfiguration::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterFormStateInitialized'][1613296803]
        = PropertyMappingConfiguration::class;

    // Register "mapmagicvh:" namespace
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['mapmagicvh'][] = 'WapplerSystems\\MapMagic\\ViewHelpers';

    // Register FE plugin
    ExtensionUtility::configurePlugin(
        'MapMagic',
        'Map',
        [FormFrontendController::class => 'render, perform'],
        [FormFrontendController::class => 'perform'],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
});
