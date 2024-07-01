<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Model\DataSources;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;


abstract class AbstractDataSource implements DataSourceInterface
{
    /**
     * @var array
     */
    protected $properties = [];
    protected string $identifier;
    protected string $type;
    protected string $label;

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
    public function initializeDataSource()
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['initializeDataSource'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'initializeDataSource')) {
                $hookObj->initializeDataSource(
                    $this
                );
            }
        }
    }


    public function setOptions(array $options, bool $resetValidators = false)
    {

        if (isset($options['properties'])) {
            foreach ($options['properties'] as $key => $value) {
                $this->setProperty($key, $value);
            }
        }

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

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }


}
