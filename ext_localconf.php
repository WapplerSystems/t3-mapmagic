<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\Map\Controller\AddressController;

defined('TYPO3') or die();

call_user_func(
    function () {

        ExtensionUtility::configurePlugin(
            'Map Magic',
            'Ajaxmap',
            [
                AddressController::class => 'ajaxSearch'
            ],
            // non-cacheable actions
            [
                AddressController::class => 'ajaxSearch'
            ]
        );


        ExtensionUtility::configurePlugin(
            'Myleaflet',
            'SingleView',
            [
                AddressController::class => 'show'
            ],
            // non-cacheable actions
            [
                AddressController::class => 'show'
            ]
        );


    }
);
