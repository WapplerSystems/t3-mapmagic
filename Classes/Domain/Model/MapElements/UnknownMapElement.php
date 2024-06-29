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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;
use WapplerSystems\MapMagic\Domain\Model\Renderable\AbstractRenderable;

/**
 * A Map Element that has no definition.
 *
 * Scope: frontend
 */
class UnknownMapElement extends AbstractRenderable implements MapElementInterface
{
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
            throw new IdentifierNotValidException('The given identifier was not a string or the string was empty.', 1382364370);
        }
        $this->identifier = $identifier;
        $this->type = $type;
    }

    /**
     * Sets up the form element
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
     * Returns a unique identifier of this element.
     * While element identifiers are only unique within one form,
     * this includes the identifier of the form itself, making it "globally" unique
     *
     * @return string the "globally" unique identifier of this element
     */
    public function getUniqueIdentifier(): string
    {
        $formDefinition = $this->getRootMap();
        $uniqueIdentifier = sprintf('%s-%s', $formDefinition->getIdentifier(), $this->identifier);
        $uniqueIdentifier = (string)preg_replace('/[^a-zA-Z0-9-_]/', '_', $uniqueIdentifier);
        return lcfirst($uniqueIdentifier);
    }

    /**
     * Get the template name of the renderable
     */
    public function getTemplateName(): string
    {
        return 'UnknownElement';
    }

    /**
     * @return mixed the default value for this Map Element
     * @internal
     */
    public function getDefaultValue()
    {
        return null;
    }

    /**
     * Not used in this implementation
     *
     * @param mixed $defaultValue the default value for this Map Element
     * @internal
     */
    public function setDefaultValue($defaultValue) {}

    /**
     * Not used in this implementation
     *
     * @param mixed $value
     * @internal
     */
    public function setProperty(string $key, $value) {}

    /**
     * @internal
     */
    public function getProperties(): array
    {
        return [];
    }

    /**
     * @internal
     */
    public function isRequired(): bool
    {
        return false;
    }
}
