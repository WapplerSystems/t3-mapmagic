<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Model\Renderable;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Model\Exception\MapDefinitionConsistencyException;
use WapplerSystems\MapMagic\Domain\Model\MapDefinition;

/**
 * Convenience base class which implements common functionality for most
 * classes which implement RenderableInterface.
 *
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 * @internal
 */
abstract class AbstractRenderable implements RenderableInterface, VariableRenderableInterface
{
    /**
     * Abstract "type" of this Renderable. Is used during the rendering process
     * to determine the template file or the View PHP class being used to render
     * the particular element.
     *
     * @var string
     */
    protected $type;

    /**
     * The identifier of this renderable
     *
     * @var string
     */
    protected $identifier;

    /**
     * The parent renderable
     *
     * @var CompositeRenderableInterface|null
     */
    protected $parentRenderable;

    /**
     * The label of this renderable
     *
     * @var string
     */
    protected $label = '';

    /**
     * associative array of rendering options
     *
     * @var array
     */
    protected $renderingOptions = [];

    /**
     * The position of this renderable inside the parent renderable.
     *
     * @var int
     */
    protected $index = 0;

    /**
     * The name of the template file of the renderable.
     *
     * @var string
     */
    protected $templateName = '';


    /**
     * Get the type of the renderable
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the identifier of the element
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Set the identifier of the element
     */
    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Set multiple properties of this object at once.
     * Every property which has a corresponding set* method can be set using
     * the passed $options array.
     */
    public function setOptions(array $options)
    {
        if (isset($options['label'])) {
            $this->setLabel($options['label']);
        }

        if (isset($options['renderingOptions'])) {
            foreach ($options['renderingOptions'] as $key => $value) {
                $this->setRenderingOption($key, $value);
            }
        }

        ArrayUtility::assertAllArrayKeysAreValid(
            $options,
            ['label', 'properties', 'renderingOptions', 'mapEditor', 'options', 'dataSourceType', 'dataSourceProperties']
        );
    }


    /**
     * Set the datatype
     */
    public function setDataType(string $dataType)
    {
        $formDefinition = $this->getRootMap();
        $formDefinition->getProcessingRule($this->getIdentifier())->setDataType($dataType);
    }

    /**
     * Get the classname of the renderer
     */
    public function getRendererClassName(): string
    {
        return $this->getRootMap()->getRendererClassName();
    }

    /**
     * Get all rendering options
     */
    public function getRenderingOptions(): array
    {
        return $this->renderingOptions;
    }

    /**
     * Set the rendering option $key to $value.
     *
     * @param mixed $value
     * @return mixed
     */
    public function setRenderingOption(string $key, $value)
    {
        if (is_array($value) && isset($this->renderingOptions[$key]) && is_array($this->renderingOptions[$key])) {
            ArrayUtility::mergeRecursiveWithOverrule($this->renderingOptions[$key], $value);
            $this->renderingOptions[$key] = ArrayUtility::removeNullValuesRecursive($this->renderingOptions[$key]);
        } elseif ($value === null) {
            unset($this->renderingOptions[$key]);
        } else {
            $this->renderingOptions[$key] = $value;
        }
    }

    /**
     * Get the parent renderable
     *
     * @return CompositeRenderableInterface|null
     */
    public function getParentRenderable()
    {
        return $this->parentRenderable;
    }

    /**
     * Set the parent renderable
     */
    public function setParentRenderable(CompositeRenderableInterface $parentRenderable)
    {
        $this->parentRenderable = $parentRenderable;
        $this->registerInMapIfPossible();
    }

    /**
     * Get the root form this element belongs to
     *
     * @throws MapDefinitionConsistencyException
     */
    public function getRootMap(): MapDefinition
    {
        $rootRenderable = $this->parentRenderable;
        while ($rootRenderable !== null && !($rootRenderable instanceof MapDefinition)) {
            $rootRenderable = $rootRenderable->getParentRenderable();
        }
        if ($rootRenderable === null) {
            throw new MapDefinitionConsistencyException(sprintf('The map element "%s" is not attached to a parent form.', $this->identifier), 1326803398);
        }

        return $rootRenderable;
    }

    /**
     * Register this element at the parent form, if there is a connection to the parent form.
     *
     * @internal
     */
    public function registerInMapIfPossible()
    {
        try {
            $rootMap = $this->getRootMap();
            $rootMap->registerRenderable($this);
        } catch (MapDefinitionConsistencyException $exception) {
        }
    }

    /**
     * Triggered when the renderable is removed from it's parent
     *
     * @internal
     */
    public function onRemoveFromParentRenderable()
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeRemoveFromParentRenderable'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'beforeRemoveFromParentRenderable')) {
                $hookObj->beforeRemoveFromParentRenderable(
                    $this
                );
            }
        }

        try {
            $rootMap = $this->getRootMap();
            $rootMap->unregisterRenderable($this);
        } catch (MapDefinitionConsistencyException $exception) {
        }
        $this->parentRenderable = null;
    }

    /**
     * Get the index of the renderable
     *
     * @internal
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Set the index of the renderable
     *
     * @internal
     */
    public function setIndex(int $index)
    {
        $this->index = $index;
    }

    /**
     * Get the label of the renderable
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set the label which shall be displayed next to the form element
     */
    public function setLabel(string $label)
    {
        $this->label = $label;
    }

    /**
     * Get the templateName name of the renderable
     */
    public function getTemplateName(): string
    {
        return empty($this->renderingOptions['templateName'])
            ? $this->type
            : $this->renderingOptions['templateName'];
    }

    /**
     * Returns whether this renderable is enabled
     */
    public function isEnabled(): bool
    {
        return !isset($this->renderingOptions['enabled']) || (bool)$this->renderingOptions['enabled'] === true;
    }


}
