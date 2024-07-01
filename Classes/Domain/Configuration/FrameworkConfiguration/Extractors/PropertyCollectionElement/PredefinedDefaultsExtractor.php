<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\PropertyCollectionElement;

use WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors\AbstractExtractor;

/**
 * @internal
 */
class PredefinedDefaultsExtractor extends AbstractExtractor
{
    /**
     * @param mixed $value
     */
    public function __invoke(string $_, $value, array $matches)
    {
        [, $propertyCollectionName, $propertyCollectionElementIdentifier, $propertyPath] = $matches;
        $propertyCollectionName = str_replace('Definition', '', $propertyCollectionName);

        $result = $this->extractorDto->getResult();
        $result['collections'][$propertyCollectionName][$propertyCollectionElementIdentifier]['predefinedDefaults'][$propertyPath] = $value;
        $this->extractorDto->setResult($result);
    }
}
