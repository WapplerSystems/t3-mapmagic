<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Runtime\Exception;

use WapplerSystems\MapMagic\Domain\Exception;

/**
 * This Exception is thrown in the FormRuntime if the PropertyMapper throws
 * a \TYPO3\CMS\Extbase\Property\Exception. It adds some more Information to
 * better understand why the PropertyMapper failed to map the properties
 */
class PropertyMappingException extends Exception {}
