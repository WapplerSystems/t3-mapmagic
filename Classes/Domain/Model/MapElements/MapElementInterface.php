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

use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;

/**
 * A base form element interface, which can be the starting point for creating
 * custom (PHP-based) Map Elements.
 *
 * A *MapElement* is a part of a *Page*, which in turn is part of a MapDefinition.
 * See {@link MapDefinition} for an in-depth explanation.
 *
 * **Often, you should rather subclass {@link AbstractMapElement} instead of
 * implementing this interface.**
 *
 * Scope: frontend
 */
interface MapElementInterface extends RenderableInterface
{
    /**
     * Will be called as soon as the element is (tried to be) added to a form
     * @see registerInMapIfPossible()
     *
     * @internal
     */
    public function initializeMapElement();

    /**
     * Returns a unique identifier of this element.
     * While element identifiers are only unique within one form,
     * this includes the identifier of the form itself, making it "globally" unique
     *
     * @return string the "globally" unique identifier of this element
     */
    public function getUniqueIdentifier(): string;

    /**
     * Set an element-specific configuration property.
     *
     * @param mixed $value
     */
    public function setProperty(string $key, $value);

    /**
     * Get all element-specific configuration properties
     */
    public function getProperties(): array;

    /**
     * Set a rendering option
     *
     * @param mixed $value
     */
    public function setRenderingOption(string $key, $value);

    /**
     * Set the target data type for this element
     *
     * @param string $dataType the target data type
     */
    public function setDataType(string $dataType);

}
