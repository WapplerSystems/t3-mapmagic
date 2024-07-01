<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\MapDefinition\Converters;

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * @internal
 */
class RemoveHmacDataConverter extends AbstractConverter
{
    /**
     * Remove the hmac data ("_orig_<propertyName>") for the corresponding property.
     *
     * @param mixed $value
     */
    public function __invoke(string $key, $value): void
    {
        $formDefinition = $this->converterDto->getMapDefinition();

        $propertyPathParts = explode('.', $key);
        array_pop($propertyPathParts);
        $propertyPath = implode('.', $propertyPathParts);
        $formDefinition = ArrayUtility::removeByPath($formDefinition, $propertyPath, '.');

        $this->converterDto->setMapDefinition($formDefinition);
    }
}
