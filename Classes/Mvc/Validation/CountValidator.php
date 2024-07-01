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
 * Validator for countable types
 *
 * Scope: frontend
 * @internal
 */
final class CountValidator extends AbstractValidator
{
    /**
     * @var array
     */
    protected $supportedOptions = [
        'minimum' => [0, 'The minimum count to accept', 'integer'],
        'maximum' => [PHP_INT_MAX, 'The maximum count to accept', 'integer'],
    ];

    /**
     * The given value is valid if it is an array or \Countable that contains the specified amount of elements.
     */
    public function isValid(mixed $value): void
    {
        if (!is_array($value) && !($value instanceof \Countable)) {
            $this->addError(
                $this->translateErrorMessage(
                    'validation.error.1475002976',
                    'map_magic'
                ),
                1475002976
            );
            return;
        }

        $minimum = (int)$this->options['minimum'];
        $maximum = (int)$this->options['maximum'];
        if (count($value) < $minimum || count($value) > $maximum) {
            $this->addError(
                $this->translateErrorMessage(
                    'validation.error.1475002994',
                    'map_magic',
                    [$minimum, $maximum]
                ),
                1475002994,
                [$this->options['minimum'], $this->options['maximum']]
            );
        }
    }
}
