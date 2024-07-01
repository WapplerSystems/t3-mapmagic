<?php

declare(strict_types=1);

namespace WapplerSystems\MapMagic;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WapplerSystems\MapMagic\ConfigurationModuleProvider\MapYamlProvider;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\ProviderRegistry;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {

    if ($containerBuilder->hasDefinition(ProviderRegistry::class)) {
        $container->services()->defaults()->autowire()->autoconfigure()->public()
            ->set('lowlevel.configuration.module.provider.mapyamlconfiguration')
            ->class(MapYamlProvider::class)
            ->tag(
                'lowlevel.configuration.module.provider',
                [
                    'identifier' => 'mapmagicYamlConfiguration',
                    'after' => 'eventListeners',
                ]
            );
    }
};
