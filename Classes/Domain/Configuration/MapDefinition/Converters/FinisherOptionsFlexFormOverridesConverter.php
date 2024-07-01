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
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

/**
 * Apply FlexForm finisher option overrides
 *
 * @internal
 */
class FinisherOptionsFlexFormOverridesConverter
{
    /**
     * @var FlexFormFinisherOverridesConverterDto
     */
    protected $converterDto;

    public function __construct(FlexFormFinisherOverridesConverterDto $converterDto)
    {
        $this->converterDto = $converterDto;
    }

    /**
     * Used for overriding finisher options with flexform settings
     * Flexform settings "win": When a setting is set in the form
     * definition and in flexform the one in flexform will overwrite the
     * one defined in the form definition.
     *
     * Here we adjust the parsed configuration and apply the overrides.
     *
     * @param string $_ unused in this context
     * @param mixed $__ unused in this context
     * @param array $matches the expression matches from the ArrayProcessor - for example matches of ^(.*)\.config\.type$
     */
    public function __invoke(string $_, $__, array $matches): void
    {
        [, $optionKey] = $matches;
        $prototypeFinisherDefinition = $this->converterDto->getPrototypeFinisherDefinition();
        $finisherDefinition = $this->converterDto->getFinisherDefinition();
        $finisherIdentifier = $this->converterDto->getFinisherIdentifier();
        $flexFormSheetSettings = $this->converterDto->getFlexFormSheetSettings();

        try {
            $value = ArrayUtility::getValueByPath(
                $flexFormSheetSettings['finishers'][$finisherIdentifier],
                $optionKey,
                '.'
            );
        } catch (MissingArrayPathException $exception) {
            return;
        }

        $fieldConfiguration = $prototypeFinisherDefinition['MapEngine']['elements'][$optionKey] ?? [];

        if ($fieldConfiguration['section'] ?? false) {
            $processedOptionValue = [];

            foreach ($value ?: [] as $optionListValue) {
                $key = $optionListValue[$fieldConfiguration['sectionItemKey']];
                $value = $optionListValue[$fieldConfiguration['sectionItemValue']];
                $processedOptionValue[$key] = $value;
            }

            if (!empty($processedOptionValue)) {
                $value = $processedOptionValue;
            }
        }

        $optionPath = 'options.' . $optionKey;

        // Skip additional translation for finisher options that were changed via flexform
        if ($this->optionValueHasChanged($finisherDefinition, $optionPath, $value)) {
            $finisherDefinition['options']['translation']['propertiesExcludedFromTranslation'][] = $optionKey;
        }

        $finisherDefinition = ArrayUtility::setValueByPath($finisherDefinition, $optionPath, $value, '.');

        $this->converterDto->setFinisherDefinition($finisherDefinition);
    }

    /**
     * Test if finisher option value differs from finisher definition.
     *
     * Compares the given finisher option value with the corresponding value in the
     * finisher definition. Returns `true` if both values are equal, `false` otherwise.
     *
     * @param array<string, mixed> $finisherDefinition
     */
    protected function optionValueHasChanged(array $finisherDefinition, string $optionPath, mixed $value): bool
    {
        try {
            return $value !== ArrayUtility::getValueByPath($finisherDefinition, $optionPath, '.');
        } catch (MissingArrayPathException) {
            return true;
        }
    }
}
