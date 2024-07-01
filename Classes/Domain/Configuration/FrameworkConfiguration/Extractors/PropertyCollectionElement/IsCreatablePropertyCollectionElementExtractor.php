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
class IsCreatablePropertyCollectionElementExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $mapElementType, $mapEditorIndex] = $matches;

        if (
            $value !== 'Inspector-FinishersEditor'
            && $value !== 'Inspector-ValidatorsEditor'
            && $value !== 'Inspector-RequiredValidatorEditor'
        ) {
            return;
        }

        $propertyCollectionName = $value === 'Inspector-FinishersEditor' ? 'finishers' : 'validators';

        $result = $this->extractorDto->getResult();

        if (
            $value === 'Inspector-FinishersEditor'
            || $value === 'Inspector-ValidatorsEditor'
        ) {
            $selectOptionsPath = implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'selectOptions',
                ]
            );
            if (!ArrayUtility::isValidPath($this->extractorDto->getPrototypeConfiguration(), $selectOptionsPath, '.')) {
                return;
            }
            $selectOptions = ArrayUtility::getValueByPath(
                $this->extractorDto->getPrototypeConfiguration(),
                $selectOptionsPath,
                '.'
            );
            foreach ($selectOptions as $selectOption) {
                $validatorIdentifier = $selectOption['value'] ?? '';
                if (empty($validatorIdentifier)) {
                    continue;
                }

                $result['mapElements'][$mapElementType]['collections'][$propertyCollectionName][$validatorIdentifier]['creatable'] = true;
            }
        } else {
            $validatorIdentifierPath = implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'validatorIdentifier',
                ]
            );
            if (!ArrayUtility::isValidPath($this->extractorDto->getPrototypeConfiguration(), $validatorIdentifierPath, '.')) {
                return;
            }
            $validatorIdentifier = ArrayUtility::getValueByPath(
                $this->extractorDto->getPrototypeConfiguration(),
                $validatorIdentifierPath,
                '.'
            );
            $result['mapElements'][$mapElementType]['collections'][$propertyCollectionName][$validatorIdentifier]['creatable'] = true;
        }

        $this->extractorDto->setResult($result);
    }
}
