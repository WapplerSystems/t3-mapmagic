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
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\MapMagic\Domain\Configuration\ArrayProcessing\ArrayProcessing;
use WapplerSystems\MapMagic\Domain\Configuration\ArrayProcessing\ArrayProcessor;
use WapplerSystems\MapMagic\Domain\Configuration\ConfigurationService;
use WapplerSystems\MapMagic\Domain\Configuration\FlexformConfiguration\Processors\FinisherOptionGenerator;
use WapplerSystems\MapMagic\Domain\Configuration\FlexformConfiguration\Processors\ProcessorDto;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\NoSuchFileException;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\ParseErrorException;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;
use WapplerSystems\MapMagic\Service\TranslationService;

/**
 * Event listener extending the flex form handling for tt_content form elements (CType: mapmagic_map):
 *
 * * Adds existing forms to flex form drop down list
 * * Adds finisher settings if Option "Override finisher settings" is active
 *
 * Scope: backend
 * @internal
 */
class DataStructureIdentifierListener
{
    /**
     * The data structure depends on a current form selection (persistenceIdentifier)
     * and if the field "overrideFinishers" is active. Add both to the identifier to
     * hand these information over to parseDataStructureByIdentifierPostProcess() hook.
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

        $identifier = $event->getIdentifier();

        $currentFlexData = [];
        if (!empty($row['pi_flexform']) && !\is_array($row['pi_flexform'])) {
            $currentFlexData = GeneralUtility::xml2array($row['pi_flexform']);
        }

        // Add selected form value
        $identifier['ext-mapmagic-persistenceIdentifier'] = '';
        if (!empty($currentFlexData['data']['sDEF']['lDEF']['settings.persistenceIdentifier']['vDEF'])) {
            $identifier['ext-mapmagic-persistenceIdentifier'] = $currentFlexData['data']['sDEF']['lDEF']['settings.persistenceIdentifier']['vDEF'];
        }

        // Add bool - finisher override active or not
        $identifier['ext-mapmagic-overrideFinishers'] = '';
        if (
            isset($currentFlexData['data']['sDEF']['lDEF']['settings.overrideFinishers']['vDEF'])
            && (int)$currentFlexData['data']['sDEF']['lDEF']['settings.overrideFinishers']['vDEF'] === 1
        ) {
            $identifier['ext-mapmagic-overrideFinishers'] = 'enabled';
        }

        $event->setIdentifier($identifier);
    }

    /**
     * Adds the list of existing form definitions to the form selection drop down
     * and adds sheets to override finisher settings if requested.
     */
    public function modifyDataStructure(AfterFlexFormDataStructureParsedEvent $event): void
    {
        $identifier = $event->getIdentifier();
        if (!isset($identifier['ext-mapmagic-persistenceIdentifier'])) {
            return;
        }

        $dataStructure = $event->getDataStructure();
        try {
            // Add list of existing forms to drop down if we find our key in the identifier
            $mapPersistenceManager = GeneralUtility::makeInstance(MapPersistenceManagerInterface::class);
            $formIsAccessible = false;
            foreach ($mapPersistenceManager->listMaps() as $map) {
                $invalidMapDefinition = $map['invalid'] ?? false;

                if ($map['persistenceIdentifier'] === $identifier['ext-mapmagic-persistenceIdentifier']) {
                    $formIsAccessible = true;
                }

                if ($invalidMapDefinition) {
                    $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.persistenceIdentifier']['config']['items'][] = [
                        'label' => $map['name'] . ' (' . $map['persistenceIdentifier'] . ')',
                        'value' => $map['persistenceIdentifier'],
                        'icon' => 'overlay-missing',
                    ];
                } else {
                    $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.persistenceIdentifier']['config']['items'][] = [
                        'label' => $map['name'] . ' (' . $map['persistenceIdentifier'] . ')',
                        'value' => $map['persistenceIdentifier'],
                        'icon' => 'content-map',
                    ];
                }
            }

            if (!empty($identifier['ext-mapmagic-persistenceIdentifier']) && !$formIsAccessible) {
                $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.persistenceIdentifier']['config']['items'][] = [
                    'label' => sprintf(
                        $this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:tt_content.preview.inaccessiblePersistenceIdentifier'),
                        $identifier['ext-mapmagic-persistenceIdentifier']
                    ),
                    'value' => $identifier['ext-mapmagic-persistenceIdentifier'],
                ];
            }

            // If a specific map is selected and if finisher override is active, add finisher sheets
            if (!empty($identifier['ext-mapmagic-persistenceIdentifier']) && $formIsAccessible) {
                $persistenceIdentifier = $identifier['ext-mapmagic-persistenceIdentifier'];
                $formDefinition = $mapPersistenceManager->load($persistenceIdentifier);

                $translationFile = 'LLL:EXT:map_magic/Resources/Private/Language/Database.xlf';
                $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.overrideFinishers'] = [
                    'label' => $translationFile . ':tt_content.pi_flexform.mapmagic.overrideFinishers',
                    'onChange' => 'reload',
                    'config' => [
                        'type' => 'check',
                    ],
                ];

                $newSheets = [];

                if (isset($formDefinition['finishers']) && !empty($formDefinition['finishers'])) {
                    $newSheets = $this->getAdditionalFinisherSheets($persistenceIdentifier, $formDefinition);
                }

                if (empty($newSheets)) {
                    ArrayUtility::mergeRecursiveWithOverrule(
                        $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.overrideFinishers'],
                        [
                            'description' => $translationFile . ':tt_content.pi_flexform.mapmagic.overrideFinishers.empty',
                            'config' => [
                                'readOnly' => true,
                            ],
                        ]
                    );
                }

                if ($identifier['ext-mapmagic-overrideFinishers'] === 'enabled') {
                    ArrayUtility::mergeRecursiveWithOverrule(
                        $dataStructure,
                        $newSheets
                    );
                }
            }
        } catch (NoSuchFileException|ParseErrorException $e) {
            $dataStructure = $this->addSelectedPersistenceIdentifier($identifier['ext-mapmagic-persistenceIdentifier'], $dataStructure);
            $this->addInvalidFrameworkConfigurationFlashMessage($e);
        }

        $event->setDataStructure($dataStructure);
    }

    /**
     * Returns additional flexform sheets with finisher fields
     *
     * @param string $persistenceIdentifier Current persistence identifier
     * @param array $formDefinition The form definition
     */
    protected function getAdditionalFinisherSheets(string $persistenceIdentifier, array $formDefinition): array
    {
        if (empty($formDefinition['finishers'])) {
            return [];
        }

        $prototypeName = $formDefinition['prototypeName'] ?? 'standard';
        $prototypeConfiguration = GeneralUtility::makeInstance(ConfigurationService::class)
            ->getPrototypeConfiguration($prototypeName);

        if (empty($prototypeConfiguration['finishersDefinition'])) {
            return [];
        }

        $formIdentifier = $formDefinition['identifier'];
        $finishersDefinition = $prototypeConfiguration['finishersDefinition'];

        $sheets = ['sheets' => []];
        foreach ($formDefinition['finishers'] as $formFinisherDefinition) {
            $finisherIdentifier = $formFinisherDefinition['identifier'];
            if (!isset($finishersDefinition[$finisherIdentifier]['MapEngine']['elements'])) {
                continue;
            }
            $sheetIdentifier = $this->buildFlexformSheetIdentifier(
                $persistenceIdentifier,
                $prototypeName,
                $formIdentifier,
                $finisherIdentifier
            );

            $finishersDefinition = $this->translateFinisherDefinitionByIdentifier(
                $finisherIdentifier,
                $finishersDefinition,
                $prototypeConfiguration
            );

            $prototypeFinisherDefinition = $finishersDefinition[$finisherIdentifier];
            $finisherLabel = $prototypeFinisherDefinition['MapEngine']['label'] ?? '';
            $sheet = $this->initializeNewSheetArray($sheetIdentifier, $finisherLabel);

            $converterDto = GeneralUtility::makeInstance(
                ProcessorDto::class,
                $finisherIdentifier,
                $prototypeFinisherDefinition,
                $formFinisherDefinition
            );

            // Remove all container elements "el" from sections beforehand.
            // These should not be matched by the regex below.
            // This greatly reduces headaches.
            $elements = $prototypeFinisherDefinition['MapEngine']['elements'];
            foreach ($elements as $key => $element) {
                if ($element['section'] ?? false) {
                    unset($elements[$key]['el']);
                }
            }

            // Iterate over all `prototypes.<prototypeName>.finishersDefinition.<finisherIdentifier>.MapEngine.elements` values
            // and convert them to FlexForm elements
            GeneralUtility::makeInstance(ArrayProcessor::class, $elements)->forEach(
                GeneralUtility::makeInstance(
                    ArrayProcessing::class,
                    'convertToFlexFormSheets',
                    // Parse top level elements and section containers.
                    '^(.*)(?:\.config\.type|\.section)$',
                    GeneralUtility::makeInstance(FinisherOptionGenerator::class, $converterDto)
                )
            );

            $sheet[$sheetIdentifier]['ROOT']['el'] = $converterDto->getResult();
            ArrayUtility::mergeRecursiveWithOverrule($sheets['sheets'], $sheet);
        }

        return $sheets;
    }

    /**
     * Boilerplate XML array of a new sheet
     *
     * @throws \InvalidArgumentException
     */
    protected function initializeNewSheetArray(string $sheetIdentifier, string $finisherName): array
    {
        if (empty($sheetIdentifier)) {
            throw new \InvalidArgumentException('$sheetIdentifier must not be empty.', 1472060918);
        }
        if (empty($finisherName)) {
            throw new \InvalidArgumentException('$finisherName must not be empty.', 1472060919);
        }

        return [
            $sheetIdentifier => [
                'ROOT' => [
                    'sheetTitle' => $finisherName,
                    'type' => 'array',
                    'el' => [],
                ],
            ],
        ];
    }

    protected function addSelectedPersistenceIdentifier(string $persistenceIdentifier, array $dataStructure): array
    {
        if (!empty($persistenceIdentifier)) {
            $dataStructure['sheets']['sDEF']['ROOT']['el']['settings.persistenceIdentifier']['config']['items'][] = [
                'label' => sprintf(
                    $this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:tt_content.preview.inaccessiblePersistenceIdentifier'),
                    $persistenceIdentifier
                ),
                'value' => $persistenceIdentifier,
            ];
        }

        return $dataStructure;
    }

    protected function addInvalidFrameworkConfigurationFlashMessage(\Exception $e): void
    {
        $messageText = sprintf(
            $this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:tt_content.preview.invalidFrameworkConfiguration.text'),
            $e->getMessage()
        );

        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier('core.template.flashMessages')
            ->enqueue(
                GeneralUtility::makeInstance(
                    FlashMessage::class,
                    $messageText,
                    $this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:tt_content.preview.invalidFrameworkConfiguration.title'),
                    ContextualFeedbackSeverity::ERROR,
                    true
                )
            );
    }

    protected function buildFlexformSheetIdentifier(
        string $persistenceIdentifier,
        string $prototypeName,
        string $formIdentifier,
        string $finisherIdentifier
    ): string {
        return md5(
            implode('', [
                $persistenceIdentifier,
                $prototypeName,
                $formIdentifier,
                $finisherIdentifier,
            ])
        );
    }

    protected function translateFinisherDefinitionByIdentifier(
        string $finisherIdentifier,
        array $finishersDefinition,
        array $prototypeConfiguration
    ): array {
        if (isset($finishersDefinition[$finisherIdentifier]['MapEngine']['translationFiles'])) {
            $translationFiles = $finishersDefinition[$finisherIdentifier]['MapEngine']['translationFiles'];
        } else {
            $translationFiles = $prototypeConfiguration['mapEngine']['translationFiles'];
        }

        $finishersDefinition[$finisherIdentifier]['MapEngine'] = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
            $finishersDefinition[$finisherIdentifier]['MapEngine'],
            $translationFiles
        );

        return $finishersDefinition;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
