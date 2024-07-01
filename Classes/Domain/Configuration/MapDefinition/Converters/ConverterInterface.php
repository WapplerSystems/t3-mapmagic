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
interface ConverterInterface
{
    public function __construct(ConverterDto $converterDto, string $sessionToken = '');

    /**
     * @param mixed $value
     */
    public function __invoke(string $key, $value);
}
