<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;
use WapplerSystems\MapMagic\Domain\Exception\TypeDefinitionNotFoundException;
use WapplerSystems\MapMagic\Domain\Exception\TypeDefinitionNotValidException;
use WapplerSystems\MapMagic\Domain\Model\Exception\MapDefinitionConsistencyException;
use WapplerSystems\MapMagic\Domain\Model\Renderable\AbstractCompositeRenderable;

/**
 * A base class for "section-like" form parts like "Page" or "Section" (which
 * is rendered as "Fieldset")
 *
 * This class contains multiple MapElements ({@link MapElementInterface}).
 *
 * Please see {@link MapDefinition} for an in-depth explanation.
 *
 * **This class is NOT meant to be sub classed by developers.**
 * Scope: frontend
 */
abstract class AbstractSection extends AbstractCompositeRenderable
{
    /**
     * Constructor. Needs the identifier and type of this element
     *
     * @param string $identifier The Section identifier
     * @param string $type The Section type
     * @throws IdentifierNotValidException if the identifier was no non-empty string
     */
    public function __construct(string $identifier, string $type)
    {
        if (!is_string($identifier) || strlen($identifier) === 0) {
            throw new IdentifierNotValidException('The given identifier was not a string or the string was empty.', 1477082501);
        }

        $this->identifier = $identifier;
        $this->type = $type;
    }

    /**
     * Get the child Map Elements
     *
     * @return MapElementInterface[] The Page's elements
     */
    public function getElements(): array
    {
        return $this->renderables;
    }

    /**
     * Get the child Map Elements
     *
     * @return MapElementInterface[] The Page's elements
     */
    public function getElementsRecursively(): array
    {
        return $this->getRenderablesRecursively();
    }

    /**
     * Add a new form element at the end of the section
     *
     * @param MapElementInterface $mapElement The form element to add
     * @throws MapDefinitionConsistencyException if MapElement is already added to a section
     */
    public function addElement(MapElementInterface $mapElement)
    {
        $this->addRenderable($mapElement);
    }

    /**
     * Create a form element with the given $identifier and attach it to this section/page.
     *
     * - Create Map Element object based on the given $typeName
     * - set defaults inside the Map Element (based on the parent form's field defaults)
     * - attach Map Element to this Section/Page
     * - return the newly created Map Element object
     *
     *
     * @param string $identifier Identifier of the new form element
     * @param string $typeName type of the new form element
     * @return MapElementInterface the newly created form element
     * @throws TypeDefinitionNotFoundException
     * @throws TypeDefinitionNotValidException
     */
    public function createElement(string $identifier, string $typeName): MapElementInterface
    {
        $formDefinition = $this->getRootMap();

        $typeDefinitions = $formDefinition->getTypeDefinitions();
        if (isset($typeDefinitions[$typeName])) {
            $typeDefinition = $typeDefinitions[$typeName];
        } else {
            $renderingOptions = $formDefinition->getRenderingOptions();
            $skipUnknownElements = isset($renderingOptions['skipUnknownElements']) && $renderingOptions['skipUnknownElements'] === true;
            if (!$skipUnknownElements) {
                throw new TypeDefinitionNotFoundException(sprintf('Type "%s" not found. Probably some configuration is missing.', $typeName), 1382364019);
            }

            $element = GeneralUtility::makeInstance(UnknownMapElement::class, $identifier, $typeName);
            $this->addElement($element);
            return $element;
        }

        if (!isset($typeDefinition['implementationClassName'])) {
            throw new TypeDefinitionNotFoundException(sprintf('The "implementationClassName" was not set in type definition "%s".', $typeName), 1325689855);
        }

        $implementationClassName = $typeDefinition['implementationClassName'];
        $element = GeneralUtility::makeInstance($implementationClassName, $identifier, $typeName);
        if (!$element instanceof MapElementInterface) {
            throw new TypeDefinitionNotValidException(sprintf('The "implementationClassName" for element "%s" ("%s") does not implement the MapElementInterface.', $identifier, $implementationClassName), 1327318156);
        }
        unset($typeDefinition['implementationClassName']);

        $this->addElement($element);
        $element->setOptions($typeDefinition);

        $element->initializeMapElement();
        return $element;
    }

    /**
     * Move MapElement $element before $referenceElement.
     *
     * Both $element and $referenceElement must be direct descendants of this Section/Page.
     *
     * @param MapElementInterface $elementToMove
     * @param MapElementInterface $referenceElement
     */
    public function moveElementBefore(MapElementInterface $elementToMove, MapElementInterface $referenceElement)
    {
        $this->moveRenderableBefore($elementToMove, $referenceElement);
    }

    /**
     * Move MapElement $element after $referenceElement
     *
     * Both $element and $referenceElement must be direct descendants of this Section/Page.
     *
     * @param MapElementInterface $elementToMove
     * @param MapElementInterface $referenceElement
     */
    public function moveElementAfter(MapElementInterface $elementToMove, MapElementInterface $referenceElement)
    {
        $this->moveRenderableAfter($elementToMove, $referenceElement);
    }

    /**
     * Remove $elementToRemove from this Section/Page
     */
    public function removeElement(MapElementInterface $elementToRemove)
    {
        $this->removeRenderable($elementToRemove);
    }
}
