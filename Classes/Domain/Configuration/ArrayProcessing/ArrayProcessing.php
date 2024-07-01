<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\ArrayProcessing;

/**
 * Helper for array processing
 *
 * Scope: frontend / backend
 * @internal
 */
class ArrayProcessing
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $expression;

    /**
     * @var callable
     */
    protected $processor;

    public function __construct(string $identifier, string $expression, callable $processor)
    {
        $this->identifier = $identifier;
        $this->expression = $expression;
        $this->processor = $processor;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getProcessor(): callable
    {
        return $this->processor;
    }
}
