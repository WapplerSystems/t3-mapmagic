<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\FrameworkConfiguration\Extractors;

/**
 * @internal
 */
class ExtractorDto
{
    /**
     * @var array
     */
    protected $prototypeConfiguration;

    /**
     * @var array
     */
    protected $result = [];

    public function __construct(array $prototypeConfiguration)
    {
        $this->prototypeConfiguration = $prototypeConfiguration;
    }

    public function getPrototypeConfiguration(): array
    {
        return $this->prototypeConfiguration;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): ExtractorDto
    {
        $this->result = $result;
        return $this;
    }
}
