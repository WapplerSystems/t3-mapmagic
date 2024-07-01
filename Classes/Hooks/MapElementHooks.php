<?php

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Error;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RootRenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;
use WapplerSystems\MapMagic\Service\TranslationService;

/**
 * Scope: frontend
 * @internal
 */
class MapElementHooks
{
    /**
     * This hook is invoked by the MapRuntime for each form element
     * **after** a form page was submitted but **before** values are
     * property-mapped, validated and pushed within the MapRuntime's `MapState`.
     *
     * @param mixed $elementValue submitted value of the element *before post processing*
     * @param array $requestArguments submitted raw request values
     * @return mixed
     * @see MapRuntime::mapAndValidate()
     * @internal
     */
    public function afterSubmit(MapRuntime $formRuntime, RenderableInterface $renderable, $elementValue, array $requestArguments = [])
    {
        if ($renderable->getType() === 'AdvancedPassword') {
            if ($elementValue['password'] !== $elementValue['confirmation']) {
                $processingRule = $renderable->getRootMap()->getProcessingRule($renderable->getIdentifier());
                $processingRule->getProcessingMessages()->addError(
                    GeneralUtility::makeInstance(
                        Error::class,
                        GeneralUtility::makeInstance(TranslationService::class)->translate('validation.error.1556283177', null, 'EXT:map_magic/Resources/Private/Language/locallang.xlf'),
                        1556283177
                    )
                );
            }
            $elementValue = $elementValue['password'];
        }

        return $elementValue;
    }

    /**
     * This is a hook that is invoked by the rendering system **before**
     * the corresponding element is rendered.
     */
    public function beforeRendering(MapRuntime $formRuntime, RootRenderableInterface $renderable)
    {
        if ($renderable->getType() === 'Date') {
            $date = $formRuntime[$renderable->getIdentifier()];
            if ($date instanceof \DateTime) {
                // @see https://www.w3.org/TR/2011/WD-html-markup-20110405/input.date.html#input.date.attrs.value
                // 'Y-m-d' = https://tools.ietf.org/html/rfc3339#section-5.6 -> full-date
                $formRuntime[$renderable->getIdentifier()] = $date->format('Y-m-d');
            }
        }
    }
}
