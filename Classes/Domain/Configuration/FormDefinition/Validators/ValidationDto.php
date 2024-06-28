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

namespace WapplerSystems\MapMagic\Domain\Configuration\FormDefinition\Validators;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ValidationDto
{
    /**
     * @var string
     */
    protected $prototypeName;

    /**
     * @var string
     */
    protected $mapElementType;

    /**
     * @var string
     */
    protected $mapElementIdentifier;

    /**
     * @var string
     */
    protected $propertyPath;

    /**
     * @var string
     */
    protected $propertyCollectionName;

    /**
     * @var string
     */
    protected $propertyCollectionElementIdentifier;

    public function __construct(
        ?string $prototypeName = null,
        ?string $mapElementType = null,
        ?string $mapElementIdentifier = null,
        ?string $propertyPath = null,
        ?string $propertyCollectionName = null,
        ?string $propertyCollectionElementIdentifier = null
    ) {
        $this->prototypeName = $prototypeName;
        $this->mapElementType = $mapElementType;
        $this->mapElementIdentifier = $mapElementIdentifier;
        $this->propertyPath = $propertyPath;
        $this->propertyCollectionName = $propertyCollectionName;
        $this->propertyCollectionElementIdentifier = $propertyCollectionElementIdentifier;
    }

    public function getPrototypeName(): string
    {
        return $this->prototypeName;
    }

    public function getFormElementType(): string
    {
        return $this->mapElementType;
    }

    public function getFormElementIdentifier(): string
    {
        return $this->mapElementIdentifier;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function getPropertyCollectionName(): string
    {
        return $this->propertyCollectionName;
    }

    public function getPropertyCollectionElementIdentifier(): string
    {
        return $this->propertyCollectionElementIdentifier;
    }

    public function hasPrototypeName(): bool
    {
        return !empty($this->prototypeName);
    }

    public function hasFormElementType(): bool
    {
        return !empty($this->mapElementType);
    }

    public function hasFormElementIdentifier(): bool
    {
        return !empty($this->mapElementIdentifier);
    }

    public function hasPropertyPath(): bool
    {
        return !empty($this->propertyPath);
    }

    public function hasPropertyCollectionName(): bool
    {
        return !empty($this->propertyCollectionName);
    }

    public function hasPropertyCollectionElementIdentifier(): bool
    {
        return !empty($this->propertyCollectionElementIdentifier);
    }

    public function withPrototypeName(string $prototypeName): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $prototypeName, $this->mapElementType, $this->mapElementIdentifier, $this->propertyPath, $this->propertyCollectionName, $this->propertyCollectionElementIdentifier);
    }

    public function withFormElementType(string $mapElementType): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $this->prototypeName, $mapElementType, $this->mapElementIdentifier, $this->propertyPath, $this->propertyCollectionName, $this->propertyCollectionElementIdentifier);
    }

    public function withFormElementIdentifier(string $mapElementIdentifier): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $this->prototypeName, $this->mapElementType, $mapElementIdentifier, $this->propertyPath, $this->propertyCollectionName, $this->propertyCollectionElementIdentifier);
    }

    public function withPropertyPath(string $propertyPath): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $this->prototypeName, $this->mapElementType, $this->mapElementIdentifier, $propertyPath, $this->propertyCollectionName, $this->propertyCollectionElementIdentifier);
    }

    public function withPropertyCollectionName(string $propertyCollectionName): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $this->prototypeName, $this->mapElementType, $this->mapElementIdentifier, $this->propertyPath, $propertyCollectionName, $this->propertyCollectionElementIdentifier);
    }

    public function withPropertyCollectionElementIdentifier(string $propertyCollectionElementIdentifier): ValidationDto
    {
        return GeneralUtility::makeInstance(self::class, $this->prototypeName, $this->mapElementType, $this->mapElementIdentifier, $this->propertyPath, $this->propertyCollectionName, $propertyCollectionElementIdentifier);
    }
}
