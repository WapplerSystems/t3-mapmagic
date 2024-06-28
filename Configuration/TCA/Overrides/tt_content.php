<?php

defined('TYPO3') or die();

call_user_func(static function () {
    // Register the plugin
    $contentTypeName = \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'MapMagic',
        'Map',
        'LLL:EXT:map_magic/Resources/Private/Language/locallang.xlf:form_new_wizard_title',
        'content-form',
        'forms',
        'LLL:EXT:map_magic/Resources/Private/Language/locallang.xlf:form_new_wizard_description',
    );

    // Add the FlexForm
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:map_magic/Configuration/FlexForms/FormFramework.xml',
        $contentTypeName
    );

    // Add the FlexForm to the show item list
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        '--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin, pi_flexform',
        $contentTypeName,
        'after:palette:headers'
    );

    $GLOBALS['TCA']['tt_content']['types'][$contentTypeName]['previewRenderer'] = \WapplerSystems\MapMagic\Hooks\FormPagePreviewRenderer::class;
});
