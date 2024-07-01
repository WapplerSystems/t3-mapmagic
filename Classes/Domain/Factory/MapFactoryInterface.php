<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */


namespace WapplerSystems\MapMagic\Domain\Factory;

use WapplerSystems\MapMagic\Domain\Model\MapDefinition;

/**
 * A Form Factory is responsible for building a {@link WapplerSystems\MapMagic\Domain\Model\MapDefinition}.
 * **Instead of implementing this interface, subclassing {@link AbstractMapFactory} is more appropriate
 * in most cases**.
 *
 * A Form Factory can be called anytime a MapDefinition should be built; in most cases
 * it is done through an invocation of a Form Rendering ViewHelper.
 *
 * Scope: frontend / backend
 */
interface MapFactoryInterface
{
    /**
     * Build a form definition, depending on some configuration.
     *
     * The configuration array is factory-specific; for example a YAML or JSON factory
     * could retrieve the path to the YAML / JSON file via the configuration array.
     *
     * @param array $configuration factory-specific configuration array
     * @param string $prototypeName The name of the "PrototypeName" to use; it is factory-specific to implement this.
     * @return MapDefinition a newly built form definition
     */
    public function build(array $configuration, ?string $prototypeName = null): MapDefinition;
}
