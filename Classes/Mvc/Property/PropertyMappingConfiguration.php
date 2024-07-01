<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\MapMagic\Mvc\Property;

use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime\Lifecycle\AfterMapStateInitializedInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Scope: frontend
 * @internal
 */
class PropertyMappingConfiguration implements AfterMapStateInitializedInterface
{
    public function __construct(protected readonly ValidatorResolver $validatorResolver) {}

    /**
     * This hook is called for each form element after the class
     * WapplerSystems\MapMagic\Domain\Factory\ArrayMapFactory has built the entire form.
     *
     * It is invoked after the static form definition is ready, but without knowing
     * about the individual state organized in `MapRuntime` and `MapState`.
     *
     * @param RenderableInterface $renderable
     * @internal
     */
    public function afterBuildingFinished(RenderableInterface $renderable)
    {

    }

    /**
     * @internal
     */
    protected function checkSaveFileMountAccess(string $saveToFileMountIdentifier): bool
    {
        if (empty($saveToFileMountIdentifier)) {
            return false;
        }

        if (PathUtility::isExtensionPath($saveToFileMountIdentifier)) {
            return false;
        }

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        try {
            $resourceFactory->getFolderObjectFromCombinedIdentifier($saveToFileMountIdentifier);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param MapRuntime $formRuntime holding current form state and static form definition
     */
    public function afterMapStateInitialized(MapRuntime $formRuntime): void
    {
        foreach ($formRuntime->getMapDefinition()->getRenderablesRecursively() as $renderable) {

        }
    }


}
