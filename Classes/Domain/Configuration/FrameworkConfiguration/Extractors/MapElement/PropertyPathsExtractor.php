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
class PropertyPathsExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        $mapElementPropertyPaths = $this->getPropertyPaths($value, $matches);

        $result = $this->extractorDto->getResult();
        $result = array_merge_recursive($result, ['mapElements' => $mapElementPropertyPaths]);
        $this->extractorDto->setResult($result);
    }

    protected function getPropertyPaths(string $value, array $matches): array
    {
        $paths = [];
        [, $mapElementType, $mapEditorIndex] = $matches;

        $paths[$mapElementType]['propertyPaths'] = [];
        $templateNamePath = implode(
            '.',
            [
                'mapElementsDefinition',
                $mapElementType,
                'mapEditor',
                'editors',
                $mapEditorIndex,
                'templateName',
            ]
        );
        $templateName = ArrayUtility::getValueByPath(
            $this->extractorDto->getPrototypeConfiguration(),
            $templateNamePath,
            '.'
        );

        // Special processing of "Inspector-GridColumnViewPortConfigurationEditor" inspector editors.
        // Expand the property path which contains a "{@viewPortIdentifier}" placeholder
        // to X property paths which contain all available placeholder replacements.
        if ($templateName === 'Inspector-GridColumnViewPortConfigurationEditor') {
            $viewPortsPath = implode(
                '.',
                [
                    'mapElementsDefinition',
                    $mapElementType,
                    'mapEditor',
                    'editors',
                    $mapEditorIndex,
                    'configurationOptions',
                    'viewPorts',
                ]
            );
            $viewPorts = ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), $viewPortsPath, '.');
            foreach ($viewPorts as $viewPort) {
                $viewPortIdentifier = $viewPort['viewPortIdentifier'];
                $propertyPath = str_replace('{@viewPortIdentifier}', $viewPortIdentifier, $value);
                $paths[$mapElementType]['propertyPaths'][] = $propertyPath;
            }
        } else {
            $paths[$mapElementType]['propertyPaths'][] = $value;
        }
        return $paths;
    }
}
