<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\MapElement;

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
        [, $mapElementType, $mapEditorIndex] = $matches;

        $templateName = ArrayUtility::getValueByPath(
            $this->extractorDto->getPrototypeConfiguration(),
            implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'templateName',
                ]
            ),
            '.'
        );

        if ($templateName === 'Inspector-FinishersEditor') {
            $propertyPath = '_finishers';
        } elseif ($templateName === 'Inspector-ValidatorsEditor') {
            $propertyPath = '_validators';
        } else {
            if ($templateName === 'Inspector-RequiredValidatorEditor') {
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

            $propertyPath = ArrayUtility::getValueByPath(
                $this->extractorDto->getPrototypeConfiguration(),
                $propertyPath,
                '.'
            );
        }

        $result = $this->extractorDto->getResult();
        $result['mapElements'][$mapElementType]['selectOptions'][$propertyPath][] = $value;
        $this->extractorDto->setResult($result);
    }
}
