<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Validation;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validator for empty values.
 *
 * Scope: frontend
 */
final class EmptyValidator extends AbstractValidator
{
    /**
     * This validator always needs to be executed even if the given value is empty.
     * See AbstractValidator::validate()
     *
     * @var bool
     */
    protected $acceptsEmptyValues = true;

    /**
     * Checks if the given property ($propertyValue) is empty (NULL, empty string, empty array or empty object).
     */
    public function isValid(mixed $value): void
    {
        if (!empty($value)) {
            $this->addError(
                $this->translateErrorMessage(
                    'validation.error.1476396435',
                    'map_magic'
                ),
                1476396435
            );
        }
    }
}
