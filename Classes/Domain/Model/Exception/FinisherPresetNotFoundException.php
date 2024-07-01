<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Model\Exception;

use WapplerSystems\MapMagic\Domain\Model\Exception;

/**
 * This exception is thrown if a Finisher Preset was not found,
 * or if the implementationClassName was not set.
 */
class FinisherPresetNotFoundException extends Exception {}
