<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\MapMagic\Domain\Factory;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Model\MapDefinition;

/**
 * Base class for custom *Form Factories*. A Form Factory is responsible for building
 * a {@link WapplerSystems\MapMagic\Domain\Model\MapDefinition}.
 *
 * Example
 * =======
 *
 * Generally, you should use this class as follows:
 *
 * <pre>
 * class MyFooBarFactory extends AbstractFormFactory {
 *   public function build(array $configuration, $prototypeName) {
 *     $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
 *     $prototypeConfiguration = $configurationService->getPrototypeConfiguration($prototypeName);
 *     $formDefinition = GeneralUtility::makeInstance(MapDefinition::class, 'nameOfMyForm', $prototypeConfiguration);
 *
 *     // now, you should call methods on $formDefinition to add pages and form elements
 *
 *     return $formDefinition;
 *   }
 * }
 * </pre>
 *
 * Scope: frontend / backend
 * **This class is meant to be sub classed by developers.**
 */
abstract class AbstractMapFactory implements MapFactoryInterface
{
    /**
     * Helper to be called by every AbstractFormFactory after everything has been built to call the "afterBuildingFinished"
     * hook on all form elements.
     */
    protected function triggerMapBuildingFinished(MapDefinition $form)
    {
        foreach ($form->getRenderablesRecursively() as $renderable) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['afterBuildingFinished'] ?? [] as $className) {
                $hookObj = GeneralUtility::makeInstance($className);
                if (method_exists($hookObj, 'afterBuildingFinished')) {
                    $hookObj->afterBuildingFinished(
                        $renderable
                    );
                }
            }
        }
    }
}
