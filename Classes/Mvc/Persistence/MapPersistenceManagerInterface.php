<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Persistence;

use TYPO3\CMS\Core\Resource\Folder;

/**
 * The form persistence manager interface
 *
 * Scope: frontend / backend
 */
interface MapPersistenceManagerInterface
{
    /**
     * Load the array form representation identified by $persistenceIdentifier, and return it
     */
    public function load(string $persistenceIdentifier): array;

    /**
     * Save the array form representation identified by $persistenceIdentifier
     */
    public function save(string $persistenceIdentifier, array $formDefinition);

    /**
     * Check whether a form with the specified $persistenceIdentifier exists
     *
     * @return bool TRUE if a form with the given $persistenceIdentifier can be loaded, otherwise FALSE
     */
    public function exists(string $persistenceIdentifier): bool;

    /**
     * Delete the form representation identified by $persistenceIdentifier
     */
    public function delete(string $persistenceIdentifier);

    /**
     * List all form definitions which can be loaded through this form persistence
     * manager.
     *
     * Returns an associative array with each item containing the keys 'name' (the human-readable name of the form)
     * and 'persistenceIdentifier' (the unique identifier for the Map Persistence Manager e.g. the path to the saved form definition).
     *
     * @return array in the format [['name' => 'Map 01', 'persistenceIdentifier' => 'path1'], [ .... ]]
     */
    public function listMaps(): array;

    /**
     * Return a list of all accessible file mount points
     *
     * @return Folder[]
     */
    public function getAccessibleMapStorageFolders(): array;

    /**
     * Return a list of all accessible extension folders
     */
    public function getAccessibleExtensionFolders(): array;

    /**
     * This takes a form identifier and returns a unique persistence identifier for it.
     */
    public function getUniquePersistenceIdentifier(string $formIdentifier, string $savePath): string;

    /**
     * Check if an identifier is already used by a formDefinition.
     */
    public function checkForDuplicateIdentifier(string $identifier): bool;
}
