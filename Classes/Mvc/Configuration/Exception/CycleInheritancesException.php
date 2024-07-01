<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Configuration\Exception;

use WapplerSystems\MapMagic\Mvc\Configuration\Exception;

/**
 * This exception is thrown if the InheritancesResolverService wants to
 * solve declared inheritances which point cyclically to themselves.
 *
 * @internal
 */
class CycleInheritancesException extends Exception {}
