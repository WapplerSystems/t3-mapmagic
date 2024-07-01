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
class FlexFormFinisherOverridesConverterDto
{
    /**
     * @var array
     */
    protected $prototypeFinisherDefinition = [];

    /**
     * @var array
     */
    protected $finisherDefinition = [];

    /**
     * @var string
     */
    protected $finisherIdentifier = '';

    /**
     * @var array
     */
    protected $flexFormSheetSettings = [];

    public function __construct(
        array $prototypeFinisherDefinition,
        array $finisherDefinition,
        string $finisherIdentifier,
        array $flexFormSheetSettings
    ) {
        $this->prototypeFinisherDefinition = $prototypeFinisherDefinition;
        $this->finisherDefinition = $finisherDefinition;
        $this->finisherIdentifier = $finisherIdentifier;
        $this->flexFormSheetSettings = $flexFormSheetSettings;
    }

    public function getPrototypeFinisherDefinition(): array
    {
        return $this->prototypeFinisherDefinition;
    }

    public function getFinisherDefinition(): array
    {
        return $this->finisherDefinition;
    }

    public function setFinisherDefinition(array $finisherDefinition): FlexFormFinisherOverridesConverterDto
    {
        $this->finisherDefinition = $finisherDefinition;

        return $this;
    }

    public function getFinisherIdentifier(): string
    {
        return $this->finisherIdentifier;
    }

    public function getFlexFormSheetSettings(): array
    {
        return $this->flexFormSheetSettings;
    }
}
