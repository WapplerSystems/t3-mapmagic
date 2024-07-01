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

namespace WapplerSystems\MapMagic\Domain\Exception;

use WapplerSystems\MapMagic\Domain\Exception;

/**
 * This exception is thrown if a Type Definition for a form element was not found,
 * or if the implementationClassName was not set.
 */
class TypeDefinitionNotFoundException extends Exception {}
