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

namespace WapplerSystems\MapMagic\Domain\Model\MapElements;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;
use WapplerSystems\MapMagic\Domain\Model\Renderable\AbstractRenderable;

/**
 * A base form element, which is the starting point for creating custom (PHP-based)
 * Map Elements.
 *
 * A *MapElement* is a part of a *Page*, which in turn is part of a MapDefinition.
 * See {@link MapDefinition} for an in-depth explanation.
 *
 * Subclassing this class is a good starting-point for implementing custom PHP-based
 * Map Elements.
 *
 * Most of the functionality and API is implemented in {@link \WapplerSystems\MapMagic\Domain\Model\Renderable\AbstractRenderable}, so
 * make sure to check out this class as well.
 *
 * Still, it is quite rare that you need to subclass this class; often
 * you can just use the {@link \WapplerSystems\MapMagic\Domain\Model\MapElements\GenericMapElement} and replace some templates.
 *
 * Scope: frontend
 * **This class is meant to be sub classed by developers.**
 */
abstract class AbstractMapElement extends AbstractRenderable implements MapElementInterface
{
    /**
     * @var array
     */
    protected $properties = [];

    /**
     * Constructor. Needs this MapElement's identifier and the MapElement type
     *
     * @param string $identifier The MapElement's identifier
     * @param string $type The Map Element Type
     * @throws IdentifierNotValidException
     */
    public function __construct(string $identifier, string $type)
    {
        if (!is_string($identifier) || strlen($identifier) === 0) {
            throw new IdentifierNotValidException('The given identifier was not a string or the string was empty.', 1477082502);
        }
        $this->identifier = $identifier;
        $this->type = $type;
    }

    /**
     * Override this method in your custom MapElements if needed
     */
    public function initializeMapElement()
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['initializeMapElement'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'initializeMapElement')) {
                $hookObj->initializeMapElement(
                    $this
                );
            }
        }
    }

    /**
     * Get the global unique identifier of the element
     */
    public function getUniqueIdentifier(): string
    {
        $formDefinition = $this->getRootMap();
        $uniqueIdentifier = sprintf('%s-%s', $formDefinition->getIdentifier(), $this->identifier);
        $uniqueIdentifier = (string)preg_replace('/[^a-zA-Z0-9_-]/', '_', $uniqueIdentifier);
        return lcfirst($uniqueIdentifier);
    }

    public function setOptions(array $options, bool $resetValidators = false)
    {
        if (isset($options['defaultValue'])) {
            $this->setDefaultValue($options['defaultValue']);
        }

        if (isset($options['properties'])) {
            foreach ($options['properties'] as $key => $value) {
                $this->setProperty($key, $value);
            }
        }

        parent::setOptions($options, $resetValidators);
    }

    /**
     * Get the default value of the element
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        $formDefinition = $this->getRootMap();
        return $formDefinition->getElementDefaultValueByIdentifier($this->identifier);
    }

    /**
     * Set the default value of the element
     *
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue)
    {
        $formDefinition = $this->getRootMap();
        $currentDefaultValue = $formDefinition->getElementDefaultValueByIdentifier($this->identifier);
        if (is_array($currentDefaultValue) && is_array($defaultValue)) {
            ArrayUtility::mergeRecursiveWithOverrule($currentDefaultValue, $defaultValue);
            $defaultValue = ArrayUtility::removeNullValuesRecursive($currentDefaultValue);
        }
        $formDefinition->addElementDefaultValue($this->identifier, $defaultValue);
    }

    /**
     * Check if the element is required
     */
    public function isRequired(): bool
    {
        foreach ($this->getValidators() as $validator) {
            if ($validator instanceof NotEmptyValidator) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set a property of the element
     *
     * @param mixed $value
     */
    public function setProperty(string $key, $value)
    {
        if (is_array($value) && isset($this->properties[$key]) && is_array($this->properties[$key])) {
            ArrayUtility::mergeRecursiveWithOverrule($this->properties[$key], $value);
            $this->properties[$key] = ArrayUtility::removeNullValuesRecursive($this->properties[$key]);
        } elseif ($value === null) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $value;
        }
    }

    /**
     * Get all properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
