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

namespace WapplerSystems\MapMagic\ViewHelpers;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use WapplerSystems\MapMagic\Domain\Factory\ArrayMapFactory;
use WapplerSystems\MapMagic\Domain\Factory\MapFactoryInterface;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * Main Entry Point to render a Form into a Fluid Template
 *
 * Usage
 * =====
 *
 * Default::
 *
 *    {namespace mapmagicvh=WapplerSystems\MapMagic\ViewHelpers}
 *    <mapmagicvh:render factoryClass="NameOfYourCustomFactoryClass" />
 *
 * The factory class must implement :php:`WapplerSystems\MapMagic\Domain\Factory\MapFactoryInterface`.
 *
 * Scope: frontend
 */
final class RenderViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('persistenceIdentifier', 'string', 'The persistence identifier for the form.', false);
        $this->registerArgument('factoryClass', 'string', 'The fully qualified class name of the factory', false, ArrayMapFactory::class);
        $this->registerArgument('prototypeName', 'string', 'Name of the prototype to use', false);
        $this->registerArgument('overrideConfiguration', 'array', 'factory specific configuration', false, []);
    }

    public function render(): ?string
    {
        $persistenceIdentifier = $this->arguments['persistenceIdentifier'];
        $factoryClass = $this->arguments['factoryClass'];
        $prototypeName = $this->arguments['prototypeName'];
        $overrideConfiguration = $this->arguments['overrideConfiguration'];
        if (!empty($persistenceIdentifier)) {
            $mapPersistenceManager = GeneralUtility::makeInstance(MapPersistenceManagerInterface::class);
            $mapConfiguration = $mapPersistenceManager->load($persistenceIdentifier);
            ArrayUtility::mergeRecursiveWithOverrule(
                $mapConfiguration,
                $overrideConfiguration
            );
            $overrideConfiguration = $mapConfiguration;
            $overrideConfiguration['persistenceIdentifier'] = $persistenceIdentifier;
        }
        if (empty($prototypeName)) {
            $prototypeName = $overrideConfiguration['prototypeName'] ?? 'standard';
        }
        // Even though getContainer() is internal, we can't get container injected here due to static scope
        /** @var MapFactoryInterface $factory */
        $factory = GeneralUtility::getContainer()->get($factoryClass);
        $mapDefinition = $factory->build($overrideConfiguration, $prototypeName);
        /** @var RenderingContext $renderingContext */
        /** @var RequestInterface $request */
        $request = $this->renderingContext->getRequest();
        $map = $mapDefinition->bind($request);
        return $map->render();
    }
}
