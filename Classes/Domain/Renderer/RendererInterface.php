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

namespace WapplerSystems\MapMagic\Domain\Renderer;

use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Base interface for Renderers. A Renderer is used to render a form.
 *
 * Scope: frontend
 * **This interface is meant to be implemented by developers, although often you
 * will subclass AbstractElementRenderer** ({@link AbstractElementRenderer}).
 */
interface RendererInterface
{
    /**
     * Note: This method is expected to call the 'beforeRendering' hook
     * on each $renderable
     *
     * @return string the rendered $formRuntime
     */
    public function render(): string;

    public function setMapRuntime(MapRuntime $formRuntime);

    public function getMapRuntime(): MapRuntime;
}
