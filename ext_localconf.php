<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\Map\Controller\MapController;

defined('TYPO3') or die();

call_user_func(
    function () {

        ExtensionUtility::configurePlugin(
            'map_magic',
            'map',
            [
                MapController::class => 'show'
            ],
            [
            ],
            ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
        );


    }
);
