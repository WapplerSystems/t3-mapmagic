<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Configuration;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;

/**
 * Class ConfigurationManagerInterface
 *
 * Scope: frontend / backend
 * @internal
 */
interface ConfigurationManagerInterface extends ExtbaseConfigurationManagerInterface
{
    public const CONFIGURATION_TYPE_YAML_SETTINGS = 'YamlSettings';
}
