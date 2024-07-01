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
 * This exception is thrown if the "identifier" for a Form, a Page or a Form Element
 * is invalid (i.e. empty or not a string)
 */
class IdentifierNotValidException extends Exception {}
