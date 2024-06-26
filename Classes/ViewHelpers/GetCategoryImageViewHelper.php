<?php
namespace WapplerSystems\Map\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;


/**
 *
 *
 * @package TYPO3
 * @subpackage map_magic
 *
 */
class GetCategoryImageViewHelper extends AbstractViewHelper
{
    protected $categoryRepository;

    public function initializeArguments()
    {
        $this->registerArgument('categoryUid', 'integer', 'The category', true, 0);
        $this->registerArgument('settings', 'array', 'The settings', true, null);
    }

    /**
     * Return category images
     *
     * @return mixed
     * @api
     */
    public function render()
    {
        $categoryUid = $this->arguments['categoryUid'];

        $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
        $fileObjects = $fileRepository->findByRelation('sys_category', 'images', intval($categoryUid));

        return $fileObjects;

    }


}
