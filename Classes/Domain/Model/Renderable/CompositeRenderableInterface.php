<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\MapMagic\Domain\Model\Renderable;

/**
 * Interface which all Form Parts must adhere to **when they have sub elements**.
 * This includes especially "MapDefinition" and "Page".
 *
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 */
interface CompositeRenderableInterface extends RenderableInterface
{
    /**
     * Returns all RenderableInterface instances of this composite renderable recursively
     *
     * @return \WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface[]
     * @internal
     */
    public function getRenderablesRecursively(): array;
}
