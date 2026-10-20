<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\MapMagic\Controller\MapFrontendController;
use WapplerSystems\MapMagic\Hooks\ImportExportHook;

defined('TYPO3') or die();

call_user_func(static function () {
    if (ExtensionManagementUtility::isLoaded('impexp')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/impexp/class.tx_impexp.php']['before_addSysFileRecord'][1530637161]
            = ImportExportHook::class . '->beforeAddSysFileRecordOnImport';
    }

    // The persistence settings (allowed folders) are read in backend context too.
    ExtensionManagementUtility::addTypoScriptSetup(
        'module.tx_mapmagic {
    settings {
        yamlConfigurations {
            10 = EXT:map_magic/Configuration/Yaml/MapSetup.yaml
        }
    }
}'
    );

    ExtensionUtility::configurePlugin(
        'MapMagic',
        'Map',
        [MapFrontendController::class => 'render'],
        // Cacheable: the map definition only changes when the file or the
        // content element changes, and both clear the page cache.
        []
    );
});
