<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\FlexformConfiguration\Processors;

/**
 * Data container for finisher FlexForm processing
 *
 * @internal
 */
class ProcessorDto
{
    /**
     * @var string
     */
    protected $finisherIdentifier;

    /**
     * @var array
     */
    protected $finisherDefinitionFromSetup;

    /**
     * @var array
     */
    protected $finisherDefinitionFromMapDefinition;

    /**
     * @var array
     */
    protected $result = [];

    public function __construct(
        string $finisherIdentifier,
        array $finisherDefinitionFromSetup,
        array $finisherDefinitionFromMapDefinition
    ) {
        $this->finisherIdentifier = $finisherIdentifier;
        $this->finisherDefinitionFromSetup = $finisherDefinitionFromSetup;
        $this->finisherDefinitionFromMapDefinition = $finisherDefinitionFromMapDefinition;
    }

    public function getFinisherIdentifier(): string
    {
        return $this->finisherIdentifier;
    }

    public function getFinisherDefinitionFromSetup(): array
    {
        return $this->finisherDefinitionFromSetup;
    }

    public function getFinisherDefinitionFromMapDefinition(): array
    {
        return $this->finisherDefinitionFromMapDefinition;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): ProcessorDto
    {
        $this->result = $result;

        return $this;
    }
}
