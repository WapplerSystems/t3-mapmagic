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

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\MapElement;

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
        [, $mapElementType, $mapEditorIndex] = $matches;

        if (
            $value !== 'Inspector-PropertyGridEditor'
            && $value !== 'Inspector-MultiSelectEditor'
            && $value !== 'Inspector-CountrySelectEditor'
            && $value !== 'Inspector-ValidationErrorMessageEditor'
            && $value !== 'Inspector-RequiredValidatorEditor'
        ) {
            return;
        }

        if ($value === 'Inspector-RequiredValidatorEditor') {
            $propertyPath = implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'configurationOptions',
                    'validationErrorMessage',
                    'propertyPath',
                ]
            );
        } else {
            $propertyPath = implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'propertyPath',
                ]
            );
        }

        $result = $this->extractorDto->getResult();

        if (ArrayUtility::isValidPath($this->extractorDto->getPrototypeConfiguration(), $propertyPath, '.')) {
            $result['mapElements'][$mapElementType]['multiValueProperties'][] = ArrayUtility::getValueByPath(
                $this->extractorDto->getPrototypeConfiguration(),
                $propertyPath,
                '.'
            );
        }

        if ($value === 'Inspector-PropertyGridEditor') {
            $result['mapElements'][$mapElementType]['multiValueProperties'][] = 'defaultValue';
        }

        $this->extractorDto->setResult($result);
    }
}
