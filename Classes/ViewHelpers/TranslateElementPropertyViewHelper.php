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
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RootRenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;
use WapplerSystems\MapMagic\Service\TranslationService;

/**
 * Translate form element properties.
 *
 * Scope: frontend / backend
 */
final class TranslateElementPropertyViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('element', RootRenderableInterface::class, 'Map Element to translate', true);
        $this->registerArgument('property', 'mixed', 'Property to translate', false);
        $this->registerArgument('renderingOptionProperty', 'mixed', 'Property to translate', false);
    }

    /**
     * Return array element by key.
     *
     * @return string|array
     */
    public function render()
    {
        self::assertArgumentTypes($this->arguments);
        $element = $this->arguments['element'];
        $property = null;
        if (!empty($this->arguments['property'])) {
            $property = $this->arguments['property'];
        } elseif (!empty($this->arguments['renderingOptionProperty'])) {
            $property = $this->arguments['renderingOptionProperty'];
        }
        if (empty($property)) {
            $propertyParts = [];
        } elseif (is_array($property)) {
            $propertyParts = $property;
        } else {
            $propertyParts = [$property];
        }
        /** @var MapRuntime $formRuntime */
        $formRuntime = $this->renderingContext
            ->getViewHelperVariableContainer()
            ->get(RenderRenderableViewHelper::class, 'formRuntime');
        return GeneralUtility::makeInstance(TranslationService::class)->translateMapElementValue($element, $propertyParts, $formRuntime);
    }

    protected static function assertArgumentTypes(array $arguments)
    {
        foreach (['property', 'renderingOptionProperty'] as $argumentName) {
            if (
                !isset($arguments[$argumentName])
                || is_string($arguments[$argumentName])
                || is_array($arguments[$argumentName])
            ) {
                continue;
            }
            throw new Exception(
                sprintf(
                    'Arguments "%s" either must be string or array',
                    $argumentName
                ),
                1504871830
            );
        }
    }
}
