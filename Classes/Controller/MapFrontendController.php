<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use WapplerSystems\MapMagic\Domain\Configuration\ConfigurationService;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * The frontend controller
 *
 * Scope: frontend
 * @internal
 */
class MapFrontendController extends ActionController
{
    /**
     * @var \WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface
     */
    protected $formPersistenceManager;

    /**
     * @internal
     */
    public function injectMapPersistenceManager(MapPersistenceManagerInterface $formPersistenceManager)
    {
        $this->formPersistenceManager = $formPersistenceManager;
    }

    /**
     * Take the form which should be rendered from the plugin settings
     * and overlay the formDefinition with additional data from
     * flexform and typoscript settings.
     * This method is used directly to display the first page from the
     * formDefinition because its cached.
     *
     * @internal
     */
    public function renderAction(): ResponseInterface
    {
        $formDefinition = [];
        if (!empty($this->settings['persistenceIdentifier'])) {
            $formDefinition = $this->formPersistenceManager->load($this->settings['persistenceIdentifier']);
            $formDefinition['persistenceIdentifier'] = $this->settings['persistenceIdentifier'];
            $formDefinition = $this->overrideByFlexFormSettings($formDefinition);
            $formDefinition = ArrayUtility::setValueByPath($formDefinition, 'renderingOptions._originalIdentifier', $formDefinition['identifier'], '.');
            $formDefinition['identifier'] .= '_' . ($this->request->getAttribute('currentContentObject')?->data['uid'] ?? '');
        }
        $this->view->assign('formConfiguration', $formDefinition);

        return $this->htmlResponse();
    }

    /**
     * This method is used to display all pages / finishers except the
     * first page because its non cached.
     *
     * @internal
     */
    public function performAction(): ResponseInterface
    {
        return new ForwardResponse('render');
    }

    /**
     * Override the formDefinition with additional data from the Flexform
     * settings. For now, only finisher settings are overridable.
     */
    protected function overrideByFlexFormSettings(array $formDefinition): array
    {
        $flexFormData = GeneralUtility::xml2array($this->request->getAttribute('currentContentObject')?->data['pi_flexform'] ?? '');

        if (!is_array($flexFormData)) {
            return $formDefinition;
        }

        if (isset($formDefinition['finishers'])) {
            $prototypeName = $formDefinition['prototypeName'] ?? 'standard';
            $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
            $prototypeConfiguration = $configurationService->getPrototypeConfiguration($prototypeName);

            foreach ($formDefinition['finishers'] as $index => $formFinisherDefinition) {
                $finisherIdentifier = $formFinisherDefinition['identifier'];

                $sheetIdentifier = $this->getFlexformSheetIdentifier($formDefinition, $prototypeName, $finisherIdentifier);
                $flexFormSheetSettings = $this->getFlexFormSettingsFromSheet($flexFormData, $sheetIdentifier);

            }
        }
        return $formDefinition;
    }

    protected function getFlexformSheetIdentifier(
        array $formDefinition,
        string $prototypeName,
        string $finisherIdentifier
    ): string {
        return md5(
            implode('', [
                $formDefinition['persistenceIdentifier'],
                $prototypeName,
                $formDefinition['identifier'],
                $finisherIdentifier,
            ])
        );
    }

    protected function getFlexFormSettingsFromSheet(
        array $flexForm,
        string $sheetIdentifier
    ): array {
        $sheetData = [];
        $sheetData['data'] = array_filter(
            $flexForm['data'] ?? [],
            static function ($key) use ($sheetIdentifier) {
                return $key === $sheetIdentifier;
            },
            ARRAY_FILTER_USE_KEY
        );

        if (empty($sheetData['data'])) {
            return [];
        }

        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);

        $sheetDataXml = $flexFormTools->flexArray2Xml($sheetData);
        return $flexFormService->convertFlexFormContentToArray($sheetDataXml)['settings'] ?? [];
    }
}
