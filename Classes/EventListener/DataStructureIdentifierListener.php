<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureIdentifierInitializedEvent;
use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\NoSuchFileException;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\ParseErrorException;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * Fills the map selection of the plugin FlexForm (CType mapmagic_map) with
 * the map definitions the current backend user may access.
 *
 * Scope: backend
 * @internal
 */
class DataStructureIdentifierListener
{
    /**
     * The data structure depends on the selected map, so it goes into the
     * identifier; otherwise the cached structure of another record is reused.
     */
    public function modifyDataStructureIdentifier(AfterFlexFormDataStructureIdentifierInitializedEvent $event): void
    {
        $row = $event->getRow();
        if (($row['CType'] ?? '') !== 'mapmagic_map'
            || $event->getTableName() !== 'tt_content'
            || $event->getFieldName() !== 'pi_flexform'
        ) {
            return;
        }

        $currentFlexData = [];
        if (!empty($row['pi_flexform']) && !\is_array($row['pi_flexform'])) {
            $currentFlexData = GeneralUtility::xml2array($row['pi_flexform']);
        }

        $identifier = $event->getIdentifier();
        $identifier['ext-mapmagic-persistenceIdentifier'] = (string)($currentFlexData['data']['sDEF']['lDEF']['settings.persistenceIdentifier']['vDEF'] ?? '');
        $event->setIdentifier($identifier);
    }

    public function modifyDataStructure(AfterFlexFormDataStructureParsedEvent $event): void
    {
        $identifier = $event->getIdentifier();
        if (!isset($identifier['ext-mapmagic-persistenceIdentifier'])) {
            return;
        }
        $selected = (string)$identifier['ext-mapmagic-persistenceIdentifier'];

        $dataStructure = $event->getDataStructure();
        $items = &$dataStructure['sheets']['sDEF']['ROOT']['el']['settings.persistenceIdentifier']['config']['items'];
        try {
            $selectedIsAccessible = false;
            foreach (GeneralUtility::makeInstance(MapPersistenceManagerInterface::class)->listMaps() as $map) {
                $selectedIsAccessible = $selectedIsAccessible || $map['persistenceIdentifier'] === $selected;
                $items[] = [
                    'label' => $map['name'] . ' (' . $map['persistenceIdentifier'] . ')',
                    'value' => $map['persistenceIdentifier'],
                    'icon' => ($map['invalid'] ?? false) ? 'overlay-missing' : 'content-map',
                ];
            }
            if ($selected !== '' && !$selectedIsAccessible) {
                $items[] = $this->inaccessibleItem($selected);
            }
        } catch (NoSuchFileException|ParseErrorException $e) {
            if ($selected !== '') {
                $items[] = $this->inaccessibleItem($selected);
            }
            $this->addInvalidFrameworkConfigurationFlashMessage($e);
        }
        unset($items);

        $event->setDataStructure($dataStructure);
    }

    /**
     * Keeps a selection the user cannot see in the list, so saving the record
     * does not silently reset another editor's choice.
     *
     * @return array<string, string>
     */
    protected function inaccessibleItem(string $persistenceIdentifier): array
    {
        return [
            'label' => sprintf(
                $this->getLanguageService()->sL('map_magic.database:tt_content.preview.inaccessiblePersistenceIdentifier'),
                $persistenceIdentifier
            ),
            'value' => $persistenceIdentifier,
        ];
    }

    protected function addInvalidFrameworkConfigurationFlashMessage(\Exception $e): void
    {
        $messageText = sprintf(
            $this->getLanguageService()->sL('map_magic.database:tt_content.preview.invalidFrameworkConfiguration.text'),
            $e->getMessage()
        );

        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier('core.template.flashMessages')
            ->enqueue(
                GeneralUtility::makeInstance(
                    FlashMessage::class,
                    $messageText,
                    $this->getLanguageService()->sL('map_magic.database:tt_content.preview.invalidFrameworkConfiguration.title'),
                    ContextualFeedbackSeverity::ERROR,
                    true
                )
            );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
