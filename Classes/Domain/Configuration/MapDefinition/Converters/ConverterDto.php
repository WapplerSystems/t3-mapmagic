<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\MapDefinition\Converters;

/**
 * @internal
 */
class ConverterDto
{
    /**
     * @var array
     */
    protected $formDefinition = [];

    /**
     * @var array
     */
    protected $renderablePathParts = [];

    /**
     * @var string
     */
    protected $mapElementIdentifier = '';

    /**
     * @var int
     */
    protected $propertyCollectionIndex = 0;

    /**
     * @var string
     */
    protected $propertyCollectionName = '';

    /**
     * @var string
     */
    protected $propertyCollectionElementIdentifier = '';

    public function __construct(array $formDefinition)
    {
        $this->formDefinition = $formDefinition;
    }

    public function getMapDefinition(): array
    {
        return $this->formDefinition;
    }

    public function setMapDefinition(array $formDefinition): ConverterDto
    {
        $this->formDefinition = $formDefinition;
        return $this;
    }

    public function getRenderablePathParts(): array
    {
        return $this->renderablePathParts;
    }

    public function setRenderablePathParts(array $renderablePathParts): ConverterDto
    {
        $this->renderablePathParts = $renderablePathParts;
        return $this;
    }

    public function getMapElementIdentifier(): string
    {
        return $this->mapElementIdentifier;
    }

    public function setMapElementIdentifier(string $mapElementIdentifier): ConverterDto
    {
        $this->mapElementIdentifier = $mapElementIdentifier;
        return $this;
    }

    public function getPropertyCollectionIndex(): int
    {
        return $this->propertyCollectionIndex;
    }

    public function setPropertyCollectionIndex(int $propertyCollectionIndex): ConverterDto
    {
        $this->propertyCollectionIndex = $propertyCollectionIndex;
        return $this;
    }

    public function getPropertyCollectionName(): string
    {
        return $this->propertyCollectionName;
    }

    public function setPropertyCollectionName(string $propertyCollectionName): ConverterDto
    {
        $this->propertyCollectionName = $propertyCollectionName;
        return $this;
    }

    public function getPropertyCollectionElementIdentifier(): string
    {
        return $this->propertyCollectionElementIdentifier;
    }

    public function setPropertyCollectionElementIdentifier(string $propertyCollectionElementIdentifier): ConverterDto
    {
        $this->propertyCollectionElementIdentifier = $propertyCollectionElementIdentifier;
        return $this;
    }
}
