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
class IsCreatableMapElementExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $mapElementType] = $matches;

        $mapElementGroup = $value;

        $result = $this->extractorDto->getResult();

        if (!ArrayUtility::isValidPath(
            $this->extractorDto->getPrototypeConfiguration(),
            'mapElementsDefinition.' . $mapElementType . '.mapEditor.groupSorting',
            '.'
        )) {
            $result['mapElements'][$mapElementType]['creatable'] = false;
            $this->extractorDto->setResult($result);
            return;
        }

        $mapElementGroups = array_keys(
            ArrayUtility::getValueByPath($this->extractorDto->getPrototypeConfiguration(), 'mapEditor.mapElementGroups', '.')
        );

        $result['mapElements'][$mapElementType]['creatable'] = in_array(
            $mapElementGroup,
            $mapElementGroups,
            true
        );

        $this->extractorDto->setResult($result);
    }
}
