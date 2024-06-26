<?php
defined('TYPO3') or die();

/*********
 * Plugins
 */

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
	'Map Magic',
    'Ajaxmap',
    'Map Magic (AjaxMap)'
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
	'Map Magic',
    'SingleView',
    'Map Magic (SingleView)'
);
