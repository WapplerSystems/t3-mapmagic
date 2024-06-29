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

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\MapMagic\Domain\Model;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;
use WapplerSystems\MapMagic\Domain\Exception\TypeDefinitionNotFoundException;
use WapplerSystems\MapMagic\Domain\Finishers\FinisherInterface;
use WapplerSystems\MapMagic\Domain\Model\Exception\DuplicateFormElementException;
use WapplerSystems\MapMagic\Domain\Model\Exception\MapDefinitionConsistencyException;
use WapplerSystems\MapMagic\Domain\Model\MapElements\FormElementInterface;
use WapplerSystems\MapMagic\Domain\Model\MapElements\Layer;
use WapplerSystems\MapMagic\Domain\Model\MapElements\MapElementInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\AbstractCompositeRenderable;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;
use WapplerSystems\MapMagic\Exception as FormException;
use WapplerSystems\MapMagic\Mvc\ProcessingRule;

/**
 * This class encapsulates a complete *Form Definition*, with all of its pages,
 * form elements, validation rules which apply and finishers which should be
 * executed when the form is completely filled in.
 *
 * It is *not modified* when the form executes.
 *
 * The Anatomy Of A Form
 * =====================
 *
 * A MapDefinition consists of multiple *Page* ({@link Layer}) objects. When a
 * form is displayed to the user, only one *Page* is visible at any given time,
 * and there is a navigation to go back and forth between the pages.
 *
 * A *Page* consists of multiple *MapElements* ({@link FormElementInterface}, {@link AbstractFormElement}),
 * which represent the input fields, textareas, checkboxes shown inside the page.
 *
 * *MapDefinition*, *Page* and *FormElement* have *identifier* properties, which
 * must be unique for each given type (i.e. it is allowed that the MapDefinition and
 * a FormElement have the *same* identifier, but two MapElements are not allowed to
 * have the same identifier.
 *
 * Simple Example
 * --------------
 *
 * Generally, you can create a MapDefinition manually by just calling the API
 * methods on it, or you use a *Form Definition Factory* to build the form from
 * another representation format such as YAML.
 *
 * /---code php
 * $formDefinition = GeneralUtility::makeInstance(FormDefinition::class, 'myForm');
 *
 * $page1 = GeneralUtility::makeInstance(Page::class, 'page1');
 * $formDefinition->addPage($page);
 *
 * $element1 = GeneralUtility::makeInstance(GenericFormElement::class, 'title', 'Textfield'); # the second argument is the type of the form element
 * $page1->addElement($element1);
 * \---
 *
 * Creating a Form, Using Abstract Form Element Types
 * =====================================================
 *
 * While you can use the {@link MapDefinition::addLayer} or {@link Layer::addElement}
 * methods and create the Page and FormElement objects manually, it is often better
 * to use the corresponding create* methods ({@link MapDefinition::createLayer}
 * and {@link Layer::createElement}), as you pass them an abstract *Form Element Type*
 * such as *Text* or *Page*, and the system **automatically
 * resolves the implementation class name and sets default values**.
 *
 * So the simple example from above should be rewritten as follows:
 *
 * /---code php
 * $prototypeConfiguration = []; // We'll talk about this later
 *
 * $formDefinition = GeneralUtility::makeInstance(FormDefinition::class, 'myForm', $prototypeConfiguration);
 * $page1 = $formDefinition->createPage('page1');
 * $element1 = $page1->addElement('title', 'Textfield');
 * \---
 *
 * Now, you might wonder how the system knows that the element *Textfield*
 * is implemented using a GenericFormElement: **This is configured in the $prototypeConfiguration**.
 *
 * To make the example from above actually work, we need to add some sensible
 * values to *$prototypeConfiguration*:
 *
 * <pre>
 * $prototypeConfiguration = [
 *   'mapElementsDefinition' => [
 *     'Page' => [
 *       'implementationClassName' => 'WapplerSystems\MapMagic\Domain\Model\MapElements\Page'
 *     ],
 *     'Textfield' => [
 *       'implementationClassName' => 'WapplerSystems\MapMagic\Domain\Model\MapElements\GenericFormElement'
 *     ]
 *   ]
 * ]
 * </pre>
 *
 * For each abstract *Form Element Type* we add some configuration; in the above
 * case only the *implementation class name*. Still, it is possible to set defaults
 * for *all* configuration options of such an element, as the following example
 * shows:
 *
 * <pre>
 * $prototypeConfiguration = [
 *   'mapElementsDefinition' => [
 *     'Page' => [
 *       'implementationClassName' => 'WapplerSystems\MapMagic\Domain\Model\MapElements\Page',
 *       'label' => 'this is the label of the page if nothing is specified'
 *     ],
 *     'Textfield' => [
 *       'implementationClassName' => 'WapplerSystems\MapMagic\Domain\Model\MapElements\GenericFormElement',
 *       'label' = >'Default Label',
 *       'defaultValue' => 'Default form element value',
 *       'properties' => [
 *         'placeholder' => 'Text which is shown if element is empty'
 *       ]
 *     ]
 *   ]
 * ]
 * </pre>
 *
 * Using Preconfigured $prototypeConfiguration
 * ---------------------------------
 *
 * Often, it is not really useful to manually create the $prototypeConfiguration array.
 *
 * Most of it comes pre-configured inside the YAML settings of the extensions,
 * and the {@link \WapplerSystems\MapMagic\Domain\Configuration\ConfigurationService} contains helper methods
 * which return the ready-to-use *$prototypeConfiguration*.
 *
 * Property Mapping and Validation Rules
 * =====================================
 *
 * Besides Pages and MapElements, the FormDefinition can contain information
 * about the *format of the data* which is inputted into the form. This generally means:
 *
 * - expected Data Types
 * - Property Mapping Configuration to be used
 * - Validation Rules which should apply
 *
 * Background Info
 * ---------------
 * You might wonder why Data Types and Validation Rules are *not attached
 * to each FormElement itself*.
 *
 * If the form should create a *hierarchical output structure* such as a multi-
 * dimensional array or a PHP object, your expected data structure might look as follows:
 * <pre>
 * - person
 * -- firstName
 * -- lastName
 * -- address
 * --- street
 * --- city
 * </pre>
 *
 * Now, let's imagine you want to edit *person.address.street* and *person.address.city*,
 * but want to validate that the *combination* of *street* and *city* is valid
 * according to some address database.
 *
 * In this case, the form elements would be configured to fill *street* and *city*,
 * but the *validator* needs to be attached to the *compound object* *address*,
 * as both parts need to be validated together.
 *
 * Connecting MapElements to the output data structure
 * ====================================================
 *
 * The *identifier* of the *FormElement* is most important, as it determines
 * where in the output structure the value which is entered by the user is placed,
 * and thus also determines which validation rules need to apply.
 *
 * Using the above example, if you want to create a FormElement for the *street*,
 * you should use the identifier *person.address.street*.
 *
 * Rendering a FormDefinition
 * ==========================
 *
 * In order to trigger *rendering* on a FormDefinition,
 * the current {@link \TYPO3\CMS\Extbase\Mvc\Request} needs to be bound to the FormDefinition,
 * resulting in a {@link \WapplerSystems\MapMagic\Domain\Runtime\MapRuntime} object which contains the *Runtime State* of the form
 * (such as the currently inserted values).
 *
 * /---code php
 * # $currentRequest and $currentResponse need to be available, f.e. inside a controller you would
 * # use $this->request. Inside a ViewHelper you would use $this->renderingContext->getRequest()
 * $form = $formDefinition->bind($currentRequest);
 *
 * # now, you can use the $form object to get information about the currently
 * # entered values into the form, etc.
 * \---
 *
 * Refer to the {@link \WapplerSystems\MapMagic\Domain\Runtime\MapRuntime} API doc for further information.
 *
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 *
 * @internal May change any time, use FormFactoryInterface to select a different FormDefinition if needed
 * @todo: Declare final in v12
 */
class MapDefinition extends AbstractCompositeRenderable
{
    /**
     * The Form's pages
     *
     * @var array<int, Layer>
     */
    protected $renderables = [];

    /**
     * Property Mapping Rules, indexed by element identifier
     *
     * @var array<string, ProcessingRule>
     */
    protected $processingRules = [];

    /**
     * Contains all elements of the form, indexed by identifier.
     * Is used as internal cache as we need this really often.
     *
     * @var array<string, MapElementInterface>
     */
    protected $elementsByIdentifier = [];

    /**
     * Form element default values in the format ['elementIdentifier' => 'default value']
     *
     * @var array<string, mixed>
     */
    protected $elementDefaultValues = [];

    /**
     * Renderer class name to be used.
     *
     * @var string
     */
    protected $rendererClassName = '';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $typeDefinitions;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $layersDefinition;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $finishersDefinition;

    /**
     * The persistence identifier of the form
     *
     * @var string
     */
    protected $persistenceIdentifier;

    /**
     * Constructor. Creates a new FormDefinition with the given identifier.
     *
     * @param string $identifier The Form Definition's identifier, must be a non-empty string.
     * @param array $prototypeConfiguration overrides form defaults of this definition
     * @param string $type element type of this form
     * @param string|null $persistenceIdentifier the persistence identifier of the form
     * @throws IdentifierNotValidException if the identifier was not valid
     */
    public function __construct(
        string $identifier,
        array $prototypeConfiguration = [],
        string $type = 'Map',
        ?string $persistenceIdentifier = null
    ) {
        $this->typeDefinitions = $prototypeConfiguration['mapElementsDefinition'] ?? [];
        $this->layersDefinition = $prototypeConfiguration['layersDefinition'] ?? [];
        $this->finishersDefinition = $prototypeConfiguration['finishersDefinition'] ?? [];

        if (!is_string($identifier) || strlen($identifier) === 0) {
            throw new IdentifierNotValidException('The given identifier was not a string or the string was empty.', 1477082503);
        }

        $this->identifier = $identifier;
        $this->type = $type;
        $this->persistenceIdentifier = (string)$persistenceIdentifier;

        if ($prototypeConfiguration !== []) {
            $this->initializeFromMapDefaults();
        }
    }

    /**
     * Initialize the form defaults of the current type
     *
     * @throws TypeDefinitionNotFoundException
     * @internal
     */
    protected function initializeFromMapDefaults()
    {
        DebugUtility::debug($this->typeDefinitions);
        if (!isset($this->typeDefinitions[$this->type])) {
            throw new TypeDefinitionNotFoundException(sprintf('Type "%s" not found. Probably some configuration is missing.', $this->type), 1474905835);
        }
        $typeDefinition = $this->typeDefinitions[$this->type];
        $this->setOptions($typeDefinition);
    }

    /**
     * Set multiple properties of this object at once.
     * Every property which has a corresponding set* method can be set using
     * the passed $options array.
     *
     * @internal
     */
    public function setOptions(array $options)
    {
        if (isset($options['rendererClassName'])) {
            $this->setRendererClassName($options['rendererClassName']);
        }
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
            ['rendererClassName', 'renderingOptions', 'layers', 'mapEditor', 'label', 'datasources', 'controls', 'styles', 'plugins']
        );
    }

    /**
     *
     *
     * @param string $identifier Identifier of the new page
     * @param string $typeName Type of the new page
     * @return Layer the newly created page
     * @throws TypeDefinitionNotFoundException
     */
    public function createLayer(string $identifier, string $typeName = 'Layer'): Layer
    {
        if (!isset($this->typeDefinitions[$typeName])) {
            throw new TypeDefinitionNotFoundException(sprintf('Type "%s" not found. Probably some configuration is missing.', $typeName), 1474905953);
        }

        $typeDefinition = $this->typeDefinitions[$typeName];

        if (!isset($typeDefinition['implementationClassName'])) {
            throw new TypeDefinitionNotFoundException(sprintf('The "implementationClassName" was not set in type definition "%s".', $typeName), 1477083126);
        }
        $implementationClassName = $typeDefinition['implementationClassName'];

        /** @var Layer $layer */
        $layer = GeneralUtility::makeInstance($implementationClassName, $identifier, $typeName);

        if (isset($typeDefinition['label'])) {
            $layer->setLabel($typeDefinition['label']);
        }

        if (isset($typeDefinition['renderingOptions'])) {
            foreach ($typeDefinition['renderingOptions'] as $key => $value) {
                $layer->setRenderingOption($key, $value);
            }
        }

        ArrayUtility::assertAllArrayKeysAreValid(
            $typeDefinition,
            ['implementationClassName', 'label', 'renderingOptions', 'mapEditor']
        );

        $this->addLayer($layer);
        return $layer;
    }

    /**
     * Add a new page at the end of the form.
     *
     * Instead of this method, you should often use {@link createLayer} instead.
     *
     * @param Layer $page
     * @throws MapDefinitionConsistencyException if Page is already added to a FormDefinition
     * @see createLayer
     */
    public function addLayer(Layer $page)
    {
        $this->addRenderable($page);
    }

    /**
     * Get the Form's pages
     *
     * @return array<int, Layer> The Form's pages in the correct order
     */
    public function getLayers(): array
    {
        return $this->renderables;
    }

    /**
     * Check whether a page with the given $index exists
     *
     * @return bool TRUE if a page with the given $index exists, otherwise FALSE
     */
    public function hasLayerWithIndex(int $index): bool
    {
        return isset($this->renderables[$index]);
    }

    /**
     * Get the page with the passed index. The first page has index zero.
     *
     * If page at $index does not exist, an exception is thrown. @param int $index
     * @return Layer the page
     * @throws FormException if the specified index does not exist
     *@see hasLayerWithIndex()
     *
     */
    public function getLayerByIndex(int $index)
    {
        if (!$this->hasLayerWithIndex($index)) {
            throw new FormException(sprintf('There is no page with an index of %d', $index), 1329233627);
        }
        return $this->renderables[$index];
    }


    /**
     * Add an element to the ElementsByIdentifier Cache.
     *
     * @throws DuplicateFormElementException
     * @internal
     */
    public function registerRenderable(RenderableInterface $renderable)
    {
        if ($renderable instanceof MapElementInterface) {
            if (isset($this->elementsByIdentifier[$renderable->getIdentifier()])) {
                throw new DuplicateFormElementException(sprintf('A form element with identifier "%s" is already part of the form.', $renderable->getIdentifier()), 1325663761);
            }
            $this->elementsByIdentifier[$renderable->getIdentifier()] = $renderable;
        }
    }

    /**
     * Remove an element from the ElementsByIdentifier cache
     *
     * @internal
     */
    public function unregisterRenderable(RenderableInterface $renderable)
    {
        if ($renderable instanceof MapElementInterface) {
            unset($this->elementsByIdentifier[$renderable->getIdentifier()]);
        }
    }

    /**
     * Get all form elements with their identifiers as keys
     *
     * @return array<string, MapElementInterface>
     */
    public function getElements(): array
    {
        return $this->elementsByIdentifier;
    }

    /**
     * Get a Form Element by its identifier
     *
     * If identifier does not exist, returns NULL.
     *
     * @param string $elementIdentifier
     * @return MapElementInterface|null The element with the given $elementIdentifier or NULL if none found
     */
    public function getElementByIdentifier(string $elementIdentifier)
    {
        return $this->elementsByIdentifier[$elementIdentifier] ?? null;
    }

    /**
     * Sets the default value of a form element
     *
     * @param string $elementIdentifier identifier of the form element. This supports property paths!
     * @param mixed $defaultValue
     * @internal
     */
    public function addElementDefaultValue(string $elementIdentifier, $defaultValue)
    {
        $this->elementDefaultValues = ArrayUtility::setValueByPath(
            $this->elementDefaultValues,
            $elementIdentifier,
            $defaultValue,
            '.'
        );
    }

    /**
     * returns the default value of the specified form element
     * or NULL if no default value was set
     *
     * @param string $elementIdentifier identifier of the form element. This supports property paths!
     * @return mixed The elements default value
     * @internal
     */
    public function getElementDefaultValueByIdentifier(string $elementIdentifier)
    {
        return ObjectAccess::getPropertyPath($this->elementDefaultValues, $elementIdentifier);
    }

    /**
     * Move $pageToMove before $referencePage
     */
    public function moveLayerBefore(Layer $pageToMove, Layer $referencePage)
    {
        $this->moveRenderableBefore($pageToMove, $referencePage);
    }

    /**
     * Move $pageToMove after $referencePage
     */
    public function moveLayerAfter(Layer $pageToMove, Layer $referencePage)
    {
        $this->moveRenderableAfter($pageToMove, $referencePage);
    }

    /**
     * Remove $pageToRemove from form
     */
    public function removeLayer(Layer $pageToRemove)
    {
        $this->removeRenderable($pageToRemove);
    }

    /**
     * Bind the current request & response to this form instance, effectively creating
     * a new "instance" of the Form.
     */
    public function bind(RequestInterface $request): MapRuntime
    {
        $mapRuntime = GeneralUtility::makeInstance(MapRuntime::class);
        $mapRuntime->setMapDefinition($this);
        $mapRuntime->setRequest($request);
        $mapRuntime->initialize();
        return $mapRuntime;
    }

    public function getProcessingRule(string $propertyPath): ProcessingRule
    {
        if (!isset($this->processingRules[$propertyPath])) {
            $this->processingRules[$propertyPath] = GeneralUtility::makeInstance(ProcessingRule::class);
        }
        return $this->processingRules[$propertyPath];
    }

    /**
     * Get all mapping rules
     *
     * @return array<string, ProcessingRule>
     * @internal
     */
    public function getProcessingRules(): array
    {
        return $this->processingRules;
    }

    /**
     * @return array<string, array<string, mixed>>
     * @internal
     */
    public function getTypeDefinitions(): array
    {
        return $this->typeDefinitions;
    }

    /**
     * @return array<string, array<string, mixed>>
     * @internal
     */
    public function getLayersDefinition(): array
    {
        return $this->layersDefinition;
    }

    /**
     * Get the persistence identifier of the form
     *
     * @internal
     */
    public function getPersistenceIdentifier(): string
    {
        return $this->persistenceIdentifier;
    }

    /**
     * Set the renderer class name
     */
    public function setRendererClassName(string $rendererClassName)
    {
        $this->rendererClassName = $rendererClassName;
    }

    /**
     * Get the classname of the renderer
     */
    public function getRendererClassName(): string
    {
        return $this->rendererClassName;
    }
}
