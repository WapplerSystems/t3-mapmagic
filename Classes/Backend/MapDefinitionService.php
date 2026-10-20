<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Backend;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * Backend operations on map definition files. Every operation goes through
 * accessibleMap(): a persistence identifier sent by the browser is only
 * trusted if it is in the list of maps the current user can see, which in
 * turn respects the user's file mounts.
 */
final readonly class MapDefinitionService
{
    /** Templates for "new map", file name in Resources/Private/NewMaps without extension. */
    public const TEMPLATES = ['locations', 'regions', 'route', 'floorplan', 'blank'];

    public function __construct(
        private MapPersistenceManagerInterface $persistenceManager,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return list<array<string, mixed>> maps with references and an editable flag
     */
    public function listMaps(): array
    {
        $references = $this->countReferences();
        return array_map(
            static fn(array $map): array => $map + [
                'references' => $references[$map['persistenceIdentifier']] ?? 0,
                'editable' => !$map['readOnly'] && !$map['invalid'],
            ],
            $this->persistenceManager->listMaps()
        );
    }

    /**
     * @return array<string, mixed> the listMaps() entry
     * @throws PersistenceManagerException if the user cannot access the map
     */
    public function accessibleMap(string $persistenceIdentifier): array
    {
        foreach ($this->persistenceManager->listMaps() as $map) {
            if ($map['persistenceIdentifier'] === $persistenceIdentifier) {
                return $map;
            }
        }
        throw new PersistenceManagerException(sprintf('Map "%s" is not accessible', $persistenceIdentifier), 1792460020);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $persistenceIdentifier): array
    {
        $this->accessibleMap($persistenceIdentifier);
        return $this->persistenceManager->load($persistenceIdentifier);
    }

    /**
     * Writes the definition. The file header is fixed server-side: the
     * browser may change the label, never the type or identifier.
     *
     * @param array<string, mixed> $definition
     */
    public function save(string $persistenceIdentifier, array $definition): void
    {
        $map = $this->accessibleMap($persistenceIdentifier);
        if ($map['readOnly']) {
            throw new PersistenceManagerException(sprintf('Map "%s" is read-only', $persistenceIdentifier), 1792460021);
        }
        $this->persistenceManager->save($persistenceIdentifier, $this->withHeader($definition, (string)$map['identifier']));
    }

    /**
     * @return array<string, string> folder identifier => readable path, where the user may create maps
     */
    public function storageFolders(): array
    {
        $folders = [];
        foreach ($this->persistenceManager->getAccessibleMapStorageFolders() as $identifier => $folder) {
            $folders[$identifier] = $folder->getStorage()->getName() . ': ' . $folder->getReadablePath();
        }
        return $folders;
    }

    /**
     * @return string persistence identifier of the new map
     */
    public function create(string $label, string $template, string $folder): string
    {
        if (!in_array($template, self::TEMPLATES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown template "%s"', $template), 1792460022);
        }
        $file = GeneralUtility::getFileAbsFileName('EXT:map_magic/Resources/Private/NewMaps/' . $template . '.map.yaml');
        $definition = Yaml::parseFile($file);
        return $this->createFromDefinition($label, is_array($definition) ? $definition : [], $folder);
    }

    /**
     * @return string persistence identifier of the copy
     */
    public function duplicate(string $persistenceIdentifier, string $label, string $folder): string
    {
        return $this->createFromDefinition($label, $this->load($persistenceIdentifier), $folder);
    }

    public function delete(string $persistenceIdentifier): void
    {
        $map = $this->accessibleMap($persistenceIdentifier);
        if (!$map['removable']) {
            throw new PersistenceManagerException(sprintf('Map "%s" cannot be removed', $persistenceIdentifier), 1792460023);
        }
        $this->persistenceManager->delete($persistenceIdentifier);
    }

    public function referenceCount(string $persistenceIdentifier): int
    {
        return $this->countReferences()[$persistenceIdentifier] ?? 0;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function createFromDefinition(string $label, array $definition, string $folder): string
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('The map needs a name', 1792460024);
        }
        if (!array_key_exists($folder, $this->persistenceManager->getAccessibleMapStorageFolders())) {
            throw new PersistenceManagerException(sprintf('Folder "%s" is not accessible', $folder), 1792460025);
        }
        $identifier = $this->persistenceManager->getUniqueIdentifier($this->slug($label));
        $persistenceIdentifier = $this->persistenceManager->getUniquePersistenceIdentifier($identifier, $folder);
        $definition['label'] = $label;
        try {
            $this->persistenceManager->save($persistenceIdentifier, $this->withHeader($definition, $identifier));
        } catch (\Throwable $e) {
            // Saving creates the file before writing it; an empty leftover would
            // block the name and show up as broken map in the list.
            if ($this->persistenceManager->exists($persistenceIdentifier)) {
                $this->persistenceManager->delete($persistenceIdentifier);
            }
            throw $e;
        }
        return $persistenceIdentifier;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function withHeader(array $definition, string $identifier): array
    {
        unset($definition['type'], $definition['identifier'], $definition['persistenceIdentifier'], $definition['invalid']);
        // Header first, so the files read naturally.
        return ['type' => 'MapMagic', 'identifier' => $identifier, 'label' => (string)($definition['label'] ?? $identifier)] + $definition;
    }

    private function slug(string $label): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)iconv('UTF-8', 'ASCII//TRANSLIT', $label)), '-'));
        return $slug !== '' ? $slug : 'map';
    }

    /**
     * Content elements (not deleted, any language/workspace) per persistence
     * identifier, read from the plugin FlexForm. Only used for hints (list,
     * delete warning), never for access decisions.
     *
     * @return array<string, int>
     */
    private function countReferences(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('pi_flexform')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('mapmagic_map')))
            ->executeQuery()
            ->fetchFirstColumn();

        $counts = [];
        foreach ($rows as $flexForm) {
            $data = GeneralUtility::xml2array((string)$flexForm);
            $identifier = is_array($data) ? (string)($data['data']['sDEF']['lDEF']['settings.persistenceIdentifier']['vDEF'] ?? '') : '';
            if ($identifier !== '') {
                $counts[$identifier] = ($counts[$identifier] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
