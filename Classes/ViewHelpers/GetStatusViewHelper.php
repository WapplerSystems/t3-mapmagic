<?php

namespace WapplerSystems\Map\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;

//use TYPO3\CMS\Core\Database\ConnectionPool;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;


/**
 *
 *
 * @package TYPO3
 * @subpackage map_magic
 *
 * for tests, not used actually
 *
 */
class GetStatusViewHelper extends AbstractViewHelper
{

    public function initializeArguments()
    {
        $this->registerArgument('addressUid', 'integer', 'The uid of the address', true);
    }

    public static function renderStatic(
        array                     $arguments,
        \Closure                  $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    )
    {
        $ttaddressRepository = GeneralUtility::makeInstance(\WapplerSystems\Map\Domain\Repository\AddressRepository::class);

        $name = $ttaddressRepository->findByUid((int)$arguments['addressUid'])->getLeafletmapicon();
        return 'World' . ' ' . $name;
    }


}
