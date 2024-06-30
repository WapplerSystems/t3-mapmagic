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
