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

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
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
    protected $formRuntime;

    public function setFormRuntime(MapRuntime $formRuntime)
    {
        $this->formRuntime = $formRuntime;
    }

    public function getFormRuntime(): MapRuntime
    {
        return $this->formRuntime;
    }
}
