<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Configuration\Exception;

use WapplerSystems\MapMagic\Domain\Exception;

/**
 * This exception is thrown if a form prototype for a given name was not found.
 */
class PrototypeNotFoundException extends Exception {}
