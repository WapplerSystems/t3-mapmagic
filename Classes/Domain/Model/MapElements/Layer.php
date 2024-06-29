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

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use WapplerSystems\MapMagic\Domain\Model\MapDefinition;
use WapplerSystems\MapMagic\Domain\Model\Renderable\CompositeRenderableInterface;
use WapplerSystems\MapMagic\Exception as MapException;

/**
 * A Page, being part of a bigger MapDefinition. It contains numerous MapElements
 * as children.
 *
 * A MapDefinition consists of multiple Pages, where only one page is visible
 * at any given time.
 *
 * Most of the API of this object is implemented in {@link AbstractSection},
 * so make sure to review this class as well.
 *
 * Please see {@link MapDefinition} for an in-depth explanation.
 *
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 */
class Layer extends AbstractMapElement
{
    /**
     * Constructor. Needs this Page's identifier
     *
     * @param string $identifier The Page's identifier
     * @param string $type The Page's type
     * @throws \WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException if the identifier was no non-empty string
     */
    public function __construct(string $identifier, string $type = 'Layer')
    {
        parent::__construct($identifier, $type);
    }

    /**
     *
     * @throws MapException
     */
    public function setParentRenderable(CompositeRenderableInterface $parentRenderable)
    {
        if (!($parentRenderable instanceof MapDefinition)) {
            throw new MapException(sprintf('The specified parentRenderable must be a MapDefinition, got "%s"', get_debug_type($parentRenderable)), 1329233747);
        }
        parent::setParentRenderable($parentRenderable);
    }


}
