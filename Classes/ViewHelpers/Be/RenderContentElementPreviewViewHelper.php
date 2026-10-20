<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\ViewHelpers\Be;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumn;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Used by the form editor.
 * Render a content element preview like the page module
 *
 * Scope: backend
 * @internal
 */
final class RenderContentElementPreviewViewHelper extends AbstractViewHelper
{
    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('contentElementUid', 'int', 'The uid of a content element');
    }

    public function render(): string
    {
        $content = '';
        $contentElementUid = $this->arguments['contentElementUid'];
        $contentRecord = BackendUtility::getRecord('tt_content', $contentElementUid);
        if (!empty($contentRecord)) {
            $backendLayout = GeneralUtility::makeInstance(BackendLayout::class, 'dummy', 'dummy', []);
            $pageRow = BackendUtility::getRecord('pages', $contentRecord['pid']);
            $pageLayoutContext = GeneralUtility::makeInstance(PageLayoutContext::class, $pageRow, $backendLayout);
            $gridColumn = GeneralUtility::makeInstance(GridColumn::class, $pageLayoutContext, []);
            $columnItem = GeneralUtility::makeInstance(GridColumnItem::class, $pageLayoutContext, $gridColumn, $contentRecord);
            return $columnItem->getPreview();
        }
        return $content;
    }
}
