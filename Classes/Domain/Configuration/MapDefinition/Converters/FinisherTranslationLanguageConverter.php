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
class FinisherTranslationLanguageConverter extends AbstractConverter
{
    /**
     * If "finishers.x.options.translation.language" is empty then set the value to "default" and remove
     * the hmac.
     *
     * @param mixed $value
     */
    public function __invoke(string $key, $value): void
    {
        if (!empty($value)) {
            return;
        }

        $formDefinition = $this->converterDto->getMapDefinition();

        $formDefinition = ArrayUtility::setValueByPath($formDefinition, $key, 'default', '.');

        $hmacPropertyPathParts = explode('.', $key);
        $lastKeySegment = array_pop($hmacPropertyPathParts);
        $hmacPropertyPathParts[] = '_orig_' . $lastKeySegment;
        $hmacValuePath = implode('.', $hmacPropertyPathParts);

        if (ArrayUtility::isValidPath($formDefinition, $hmacValuePath, '.')) {
            $formDefinition = ArrayUtility::removeByPath($formDefinition, $hmacValuePath, '.');
        }

        $this->converterDto->setMapDefinition($formDefinition);
    }
}
