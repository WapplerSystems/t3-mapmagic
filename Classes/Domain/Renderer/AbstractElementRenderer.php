<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Renderer;

use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Abstract renderer which can be used as base class for custom renderers.
 *
 * Scope: frontend
 * **This class is meant to be sub classed by developers**.
 */
abstract class AbstractElementRenderer implements RendererInterface
{
    /**
     * @var \WapplerSystems\MapMagic\Domain\Runtime\MapRuntime
     */
    protected $mapRuntime;

    public function setMapRuntime(MapRuntime $formRuntime)
    {
        $this->mapRuntime = $formRuntime;
    }

    public function getMapRuntime(): MapRuntime
    {
        return $this->mapRuntime;
    }
}
