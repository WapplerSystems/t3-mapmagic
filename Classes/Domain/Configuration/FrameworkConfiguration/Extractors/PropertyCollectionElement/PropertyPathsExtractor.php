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

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\PropertyCollectionElement;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\AbstractExtractor;

/**
 * @internal
 */
class PropertyPathsExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $mapElementType, $propertyCollectionName, $propertyCollectionIndex] = $matches;

        $identifierPath = implode(
            '.',
            [
                'mapElementsDefinition',
                $mapElementType,
                'mapEditor',
                'propertyCollections',
                $propertyCollectionName,
                $propertyCollectionIndex,
                'identifier',
            ]
        );
        $identifier = ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), $identifierPath, '.');

        $result = $this->extractorDto->getResult();
        $result['mapElements'][$mapElementType]['collections'][$propertyCollectionName][$identifier]['propertyPaths'][] = $value;
        $this->extractorDto->setResult($result);
    }
}
