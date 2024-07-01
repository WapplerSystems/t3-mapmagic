<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */


namespace WapplerSystems\MapMagic\Domain\Model\DataSources;

use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;

/**
 *
 *
 * Scope: frontend
 */
interface DataSourceInterface
{
    /**
     *
     * @internal
     */
    public function initializeDataSource();

    /**
     * Set an element-specific configuration property.
     *
     * @param mixed $value
     */
    public function setProperty(string $key, $value);

    /**
     * Get all element-specific configuration properties
     */
    public function getProperties(): array;

    public function setLabel(string $label): void;

}
