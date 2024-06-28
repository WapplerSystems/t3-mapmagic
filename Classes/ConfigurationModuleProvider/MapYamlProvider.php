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

namespace WapplerSystems\MapMagic\ConfigurationModuleProvider;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use WapplerSystems\MapMagic\Mvc\Configuration\ConfigurationManager;
use WapplerSystems\MapMagic\Mvc\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\ProviderInterface;

class MapYamlProvider implements ProviderInterface
{
    protected string $identifier;
    protected ConfigurationManager $configurationManager;

    public function __construct(ConfigurationManager $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    public function __invoke(array $attributes): self
    {
        $this->identifier = $attributes['identifier'];
        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:map_magic/Resources/Private/Language/locallang.xlf:form.configuration.module.provider'
        );
    }

    public function getConfiguration(): array
    {
        $configuration = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_YAML_SETTINGS,
            'form'
        );
        ArrayUtility::naturalKeySortRecursive($configuration);
        return $configuration;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
