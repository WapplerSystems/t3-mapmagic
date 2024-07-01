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
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 */
interface VariableRenderableInterface
{
    /**
     * Set multiple properties of this object at once.
     * Every property which has a corresponding set* method can be set using
     * the passed $options array.
     *
     * @internal
     */
    public function setOptions(array $options);


}
