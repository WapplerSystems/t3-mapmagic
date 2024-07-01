<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\PropertyCollectionElement;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\AbstractExtractor;

/**
 * @internal
 */
class SelectOptionsExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $mapElementType, $propertyCollectionName, $propertyCollectionIndex, $propertyCollectionEditorIndex] = $matches;

        $propertyPath = ArrayUtility::getValueByPath(
            $this->extractorDto->getPrototypeConfiguration(),
            implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'propertyCollections',
                    $propertyCollectionName,
                    $propertyCollectionIndex,
                    'editors',
                    $propertyCollectionEditorIndex,
                    'propertyPath',
                ]
            ),
            '.'
        );

        $propertyCollectionElementIdentifier = ArrayUtility::getValueByPath(
            $this->extractorDto->getPrototypeConfiguration(),
            implode(
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
            ),
            '.'
        );

        $propertyCollectionName = str_replace('Definition', '', $propertyCollectionName);

        $result = $this->extractorDto->getResult();
        $result['collections'][$propertyCollectionName][$propertyCollectionElementIdentifier]['selectOptions'][$propertyPath][] = $value;
        $this->extractorDto->setResult($result);
    }
}
