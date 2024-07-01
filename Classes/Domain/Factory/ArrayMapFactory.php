<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */


namespace WapplerSystems\MapMagic\Domain\Factory;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Configuration\ConfigurationService;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;
use WapplerSystems\MapMagic\Domain\Exception\RenderingException;
use WapplerSystems\MapMagic\Domain\Exception\UnknownCompositRenderableException;
use WapplerSystems\MapMagic\Domain\Model\MapElements\AbstractSection;
use WapplerSystems\MapMagic\Domain\Model\MapDefinition;
use WapplerSystems\MapMagic\Domain\Model\Renderable\CompositeRenderableInterface;

/**
 * A factory that creates a MapDefinition from an array
 *
 * Scope: frontend / backend
 */
class ArrayMapFactory extends AbstractMapFactory
{
    /**
     * Build a form definition, depending on some configuration.
     *
     * @throws RenderingException
     * @internal
     */
    public function build(array $configuration, ?string $prototypeName = null): MapDefinition
    {
        if (empty($prototypeName)) {
            $prototypeName = $configuration['prototypeName'] ?? 'standard';
        }
        $persistenceIdentifier = $configuration['persistenceIdentifier'] ?? null;

        if ($configuration['invalid'] ?? false) {
            throw new RenderingException($configuration['label'], 1529710560);
        }

        $prototypeConfiguration = GeneralUtility::makeInstance(ConfigurationService::class)
            ->getPrototypeConfiguration($prototypeName);

        $map = GeneralUtility::makeInstance(
            MapDefinition::class,
            $configuration['identifier'],
            $prototypeConfiguration,
            'Map',
            $persistenceIdentifier
        );
        if (isset($configuration['layers'])) {
            foreach ($configuration['layers'] as $layerConfiguration) {
                $this->addLayer($layerConfiguration, $map);
            }
        }
        if (isset($configuration['dataSources'])) {
            foreach ($configuration['dataSources'] as $dataSourceConfiguration) {
                $this->addDataSource($dataSourceConfiguration, $map);
            }
        }

        unset($configuration['persistenceIdentifier']);
        unset($configuration['prototypeName']);
        unset($configuration['layers']);
        unset($configuration['styles']);
        unset($configuration['plugins']);
        unset($configuration['controls']);
        unset($configuration['dataSources']);
        unset($configuration['type']);
        unset($configuration['identifier']);
        $map->setOptions($configuration);

        $this->triggerMapBuildingFinished($map);

        return $map;
    }

    /**
     * Add form elements to the $parentRenderable
     *
     * @return mixed
     * @throws IdentifierNotValidException
     * @throws UnknownCompositRenderableException
     */
    protected function addLayer(array $layerConfiguration, CompositeRenderableInterface $parentRenderable)
    {
        if (!isset($layerConfiguration['identifier'])) {
            throw new IdentifierNotValidException('Identifier not set.', 1329289436);
        }
        if ($parentRenderable instanceof MapDefinition) {
            $renderable = $parentRenderable->createLayer($layerConfiguration['identifier'], $layerConfiguration['type']);
        } else {
            throw new UnknownCompositRenderableException('Unknown composit renderable "' . get_class($parentRenderable) . '"', 1479593622);
        }

        if (isset($layerConfiguration['renderables']) && is_array($layerConfiguration['renderables'])) {
            $childRenderables = $layerConfiguration['renderables'];
        } else {
            $childRenderables = [];
        }

        unset($layerConfiguration['type']);
        unset($layerConfiguration['identifier']);
        unset($layerConfiguration['dataSource']);

        $renderable->setOptions($layerConfiguration);

        if ($renderable instanceof CompositeRenderableInterface) {
            foreach ($childRenderables as $elementConfiguration) {
                $this->addLayer($elementConfiguration, $renderable);
            }
        }

        return $renderable;
    }


    protected function addDataSource(array $dataSourceConfiguration, CompositeRenderableInterface $parentRenderable)
    {
        if (!isset($dataSourceConfiguration['identifier'])) {
            throw new IdentifierNotValidException('Identifier not set.', 1329289436);
        }
        if ($parentRenderable instanceof MapDefinition) {
            $dataSource = $parentRenderable->createDataSource($dataSourceConfiguration['identifier'], $dataSourceConfiguration['type']);
        } else {
            throw new UnknownCompositRenderableException('Unknown composit renderable "' . get_class($parentRenderable) . '"', 1479593622);
        }

        unset($dataSourceConfiguration['type']);
        unset($dataSourceConfiguration['identifier']);
        unset($dataSourceConfiguration['dataSource']);

        $dataSource->setOptions($dataSourceConfiguration);

        return $dataSource;
    }

}
