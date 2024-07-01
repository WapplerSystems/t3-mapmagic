<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RootRenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;
use WapplerSystems\MapMagic\Service\TranslationService;

/**
 * Translate form element properties.
 *
 * Scope: frontend / backend
 */
final class TranslateElementErrorViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    public function initializeArguments(): void
    {
        $this->registerArgument('element', RootRenderableInterface::class, 'Map Element to translate', true);
        $this->registerArgument('error', Error::class, 'Error', true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $element = $arguments['element'];
        $error = $arguments['error'];

        /** @var MapRuntime $formRuntime */
        $formRuntime = $renderingContext
            ->getViewHelperVariableContainer()
            ->get(RenderRenderableViewHelper::class, 'formRuntime');

        return GeneralUtility::makeInstance(TranslationService::class)->translateMapElementError(
            $element,
            $error->getCode(),
            $error->getArguments(),
            $error->__toString(),
            $formRuntime
        );
    }
}
