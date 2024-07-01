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
abstract class AbstractExtractor implements ExtractorInterface
{
    /**
     * @var ExtractorDto
     */
    protected $extractorDto;

    public function __construct(ExtractorDto $extractorDto)
    {
        $this->extractorDto = $extractorDto;
    }
}
