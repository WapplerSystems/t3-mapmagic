<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Hooks;

use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Error\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\NoSuchFileException;
use WapplerSystems\MapMagic\Mvc\Configuration\Exception\ParseErrorException;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * Contains a preview rendering for the page module of CType="mapmagic_map"
 * @internal
 */
class MapPagePreviewRenderer extends StandardContentPreviewRenderer
{
    private const L10N_PREFIX = 'LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:';

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $row = $item->getRecord();
        $itemContent = $this->linkEditContent('<strong>' . htmlspecialchars($item->getContext()->getContentTypeLabels()['mapmagic_map']) . '</strong>', $row) . '<br />';

        // v14: getRecord() returns a Record object; the raw FlexForm XML is only available via getRow()
        $flexFormData = GeneralUtility::makeInstance(FlexFormService::class)
            ->convertFlexFormContentToArray((string)($item->getRow()['pi_flexform'] ?? ''));

        $persistenceIdentifier = $flexFormData['settings']['persistenceIdentifier'] ?? '';
        if (!empty($persistenceIdentifier)) {
            try {
                $mapPersistenceManager = GeneralUtility::makeInstance(MapPersistenceManagerInterface::class);

                try {
                    if (
                        $mapPersistenceManager->hasValidFileExtension($persistenceIdentifier)
                        || PathUtility::isExtensionPath($persistenceIdentifier)
                    ) {
                        $mapDefinition = $mapPersistenceManager->load($persistenceIdentifier);
                        $mapLabel = $mapDefinition['label'];
                    } else {
                        $mapLabel = sprintf(
                            $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.inaccessiblePersistenceIdentifier'),
                            $persistenceIdentifier
                        );
                    }
                } catch (ParseErrorException $e) {
                    $mapLabel = sprintf(
                        $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.invalidPersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                } catch (PersistenceManagerException $e) {
                    $mapLabel = sprintf(
                        $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.inaccessiblePersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                } catch (Exception $e) {
                    $mapLabel = sprintf(
                        $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.notExistingdPersistenceIdentifier'),
                        $persistenceIdentifier
                    );
                }
            } catch (NoSuchFileException $e) {
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $mapLabel = sprintf(
                    $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.notExistingdPersistenceIdentifier'),
                    $persistenceIdentifier
                );
            } catch (ParseErrorException $e) {
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $mapLabel = sprintf(
                    $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration'),
                    $persistenceIdentifier
                );
            } catch (\Exception $e) {
                // Top level catch - FAL throws top level exceptions on missing files, eg. in getFileInfoByIdentifier() of LocalDriver
                $this->addInvalidFrameworkConfigurationFlashMessage($persistenceIdentifier, $e);
                $mapLabel = sprintf(
                    $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.text'),
                    $persistenceIdentifier,
                    $e->getMessage()
                );
            }
        } else {
            $mapLabel = $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.noPersistenceIdentifier');
        }

        $itemContent .= $this->linkEditContent(
            htmlspecialchars($mapLabel),
            $row
        ) . '<br />';

        return $itemContent;
    }

    protected function addInvalidFrameworkConfigurationFlashMessage(string $persistenceIdentifier, \Exception $e): void
    {
        $messageText = sprintf(
            $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.text'),
            $persistenceIdentifier,
            $e->getMessage()
        );

        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier('core.template.flashMessages')
            ->enqueue(
                GeneralUtility::makeInstance(
                    FlashMessage::class,
                    $messageText,
                    $this->getLanguageService()->sL(self::L10N_PREFIX . 'tt_content.preview.invalidFrameworkConfiguration.title'),
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
