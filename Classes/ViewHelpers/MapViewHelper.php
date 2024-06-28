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

namespace WapplerSystems\MapMagic\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Custom form ViewHelper that renders the map
 *
 * Scope: frontend
 */
final class MapViewHelper extends AbstractViewHelper
{



    protected function getMapRuntime(): MapRuntime
    {
        return $this->arguments['object'];
    }
}
