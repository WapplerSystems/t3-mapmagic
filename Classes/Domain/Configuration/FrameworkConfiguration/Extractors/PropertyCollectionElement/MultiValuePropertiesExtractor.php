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
class MultiValuePropertiesExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $mapElementType, $propertyCollectionName, $propertyCollectionIndex, $propertyCollectionEditorIndex] = $matches;

        if (
            $value !== 'Inspector-PropertyGridEditor'
            && $value !== 'Inspector-MultiSelectEditor'
            && $value !== 'Inspector-CountrySelectEditor'
            && $value !== 'Inspector-ValidationErrorMessageEditor'
        ) {
            return;
        }

        $propertyPath = implode(
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
        );
        $propertyValue = ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), $propertyPath, '.');

        $result = $this->extractorDto->getResult();

        if (
            $value === 'Inspector-PropertyGridEditor'
            || $value === 'Inspector-MultiSelectEditor'
        ) {
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

            $result['mapElements'][$mapElementType]['collections'][$propertyCollectionName][$identifier]['multiValueProperties'][] = $propertyValue;
            if ($value === 'Inspector-PropertyGridEditor') {
                $result['mapElements'][$mapElementType]['collections'][$propertyCollectionName][$identifier]['multiValueProperties'][] = 'defaultValue';
            }
        } else {
            $result['mapElements'][$mapElementType]['multiValueProperties'][] = $propertyValue;
        }

        $this->extractorDto->setResult($result);
    }
}
