<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use WapplerSystems\MapMagic\Mvc\Configuration\ConfigurationManagerInterface;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * The abstract form backend controller
 *
 * Scope: backend
 * @internal
 */
abstract class AbstractBackendController extends ActionController
{
    protected array $mapSettings;
    protected MapPersistenceManagerInterface $mapPersistenceManager;

    public function injectMapPersistenceManager(MapPersistenceManagerInterface $formPersistenceManager)
    {
        $this->mapPersistenceManager = $formPersistenceManager;
    }

    public function initializeObject()
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        $this->mapSettings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_YAML_SETTINGS, 'map_magic');
        if (!isset($this->mapSettings['mapManager'])) {
            // Config sub array mapManager is crucial and should always exist. If it does
            // not, this indicates an issue in config loading logic. Except in this case.
            throw new \LogicException('Configuration could not be loaded', 1681549038);
        }
    }

    /**
     * The functionality of this method has been removed because it caused problems with open_basedir restrictions.
     * See https://forge.typo3.org/issues/98545 for details.
     * This method will be removed in TYPO3 v13.
     */
    protected function resolveResourcePaths(array $resourcePaths): array
    {
        return $resourcePaths;
    }
}
