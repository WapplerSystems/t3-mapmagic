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
 * @internal
 */
abstract class AbstractProcessor implements ProcessorInterface
{
    /**
     * @var ProcessorDto
     */
    protected $converterDto;

    public function __construct(ProcessorDto $converterDto)
    {
        $this->converterDto = $converterDto;
    }
}
