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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Fluid\View\TemplateView;
use WapplerSystems\MapMagic\Domain\Configuration\ConfigurationService;
use WapplerSystems\MapMagic\Domain\Configuration\MapDefinitionConversionService;
use WapplerSystems\MapMagic\Domain\Exception\RenderingException;
use WapplerSystems\MapMagic\Domain\Factory\ArrayMapFactory;
use WapplerSystems\MapMagic\Exception;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;
use WapplerSystems\MapMagic\Service\TranslationService;
use WapplerSystems\MapMagic\Type\MapDefinitionArray;

/**
 * The form editor controller
 *
 * Scope: backend
 * @internal
 */
class MapEditorController extends AbstractBackendController
{
    protected const JS_MODULE_NAMES = ['app', 'mediator', 'viewModel'];

    protected array $prototypeConfiguration;

    public function __construct(
        protected readonly ModuleTemplateFactory          $moduleTemplateFactory,
        protected readonly PageRenderer                   $pageRenderer,
        protected readonly IconFactory                    $iconFactory,
        protected readonly MapDefinitionConversionService $formDefinitionConversionService,
    ) {}

    /**
     * Display the form editor.
     *
     * @throws PersistenceManagerException
     */
    public function indexAction(string $formPersistenceIdentifier, ?string $prototypeName = null): ResponseInterface
    {
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($formPersistenceIdentifier)) {
            throw new PersistenceManagerException(sprintf('Read "%s" is not allowed', $formPersistenceIdentifier), 1614500662);
        }

        if (
            PathUtility::isExtensionPath($formPersistenceIdentifier)
            && !$this->mapSettings['persistenceManager']['allowSaveToExtensionPaths']
        ) {
            throw new PersistenceManagerException('Edit an extension formDefinition is not allowed.', 1478265661);
        }

        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
        $formDefinition = $this->mapPersistenceManager->load($formPersistenceIdentifier);

        if ($prototypeName === null) {
            $prototypeName = $formDefinition['prototypeName'] ?? 'standard';
        } else {
            // Loading a form definition with another prototype is currently not implemented but is planned in the future.
            // This safety check is a preventive measure.
            $selectablePrototypeNames = $configurationService->getSelectablePrototypeNamesDefinedInMapEditorSetup();
            if (!in_array($prototypeName, $selectablePrototypeNames, true)) {
                throw new Exception(sprintf('The prototype name "%s" is not configured within "mapManager.selectablePrototypesConfiguration" ', $prototypeName), 1528625039);
            }
        }

        $formDefinition['prototypeName'] = $prototypeName;
        $this->prototypeConfiguration = $configurationService->getPrototypeConfiguration($prototypeName);

        $formDefinition = $this->transformMapDefinitionForMapEditor($formDefinition);
        $mapMagicDefinitions = $this->getMapEditorDefinitions();

        $additionalViewModelJavaScriptModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::create($name),
            $this->prototypeConfiguration['mapMagic']['dynamicJavaScriptModules']['additionalViewModelModules'] ?? []
        );
        $additionalViewModelRequireJsModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::forRequireJS($name, null, true),
            $this->prototypeConfiguration['mapMagic']['dynamicRequireJsModules']['additionalViewModelModules'] ?? []
        );
        if (count($additionalViewModelRequireJsModules) > 0) {
            trigger_error(
                'mapMagic.dynamicRequireJsModules has been deprecated in v12 and will be removed with v13. ' .
                'Use mapMagic.dynamicJavaScriptModules instead.',
                E_USER_DEPRECATED
            );
            $this->pageRenderer->loadRequireJs();
        }
        $additionalViewModelModules = [...$additionalViewModelRequireJsModules, ...$additionalViewModelJavaScriptModules];
        array_map($this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(...), $additionalViewModelJavaScriptModules);
        $mapMagicAppInitialData = [
            'mapMagicDefinitions' => $mapMagicDefinitions,
            'formDefinition' => $formDefinition,
            'formPersistenceIdentifier' => $formPersistenceIdentifier,
            'prototypeName' => $prototypeName,
            'endpoints' => [
                'mapPageRenderer' => $this->uriBuilder->uriFor('renderMapPage'),
                'saveMap' => $this->uriBuilder->uriFor('saveMap'),
            ],
            'additionalViewModelModules' => $additionalViewModelModules,
            'maximumUndoSteps' => $this->prototypeConfiguration['mapMagic']['maximumUndoSteps'],
        ];

        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assign('mapMagicTemplates', $this->renderMapEditorTemplates($mapMagicDefinitions));

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $addInlineSettings = [
            'MapEditor' => [
                'typo3WinBrowserUrl' => (string)$uriBuilder->buildUriFromRoute('wizard_element_browser'),
            ],
        ];

        $addInlineSettings = array_replace_recursive(
            $addInlineSettings,
            $this->prototypeConfiguration['mapMagic']['addInlineSettings']
        );

        if (json_encode($mapMagicAppInitialData) === false) {
            throw new Exception('The form editor app data could not be encoded', 1628677079);
        }

        $javaScriptModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::create($name),
            array_filter(
                $this->prototypeConfiguration['mapMagic']['dynamicJavaScriptModules'] ?? [],
                fn(string $name) => in_array($name, self::JS_MODULE_NAMES, true),
                ARRAY_FILTER_USE_KEY
            )
        );
        $requireJsModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::forRequireJS($name),
            array_filter(
                $this->prototypeConfiguration['mapMagic']['dynamicRequireJsModules'] ?? [],
                fn(string $name) => in_array($name, self::JS_MODULE_NAMES, true),
                ARRAY_FILTER_USE_KEY
            )
        );
        if (count($requireJsModules)) {
            trigger_error(
                'mapMagic.dynamicRequireJsModules has been deprecated in v12 and will be removed with v13. ' .
                'Use mapMagic.dynamicJavaScriptModules instead.',
                E_USER_DEPRECATED
            );
            $this->pageRenderer->loadRequireJs();
        }
        $jsModules = $requireJsModules + $javaScriptModules;
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@typo3/form/backend/helper.js', 'Helper')
                ->invoke('dispatchMapEditor', $jsModules, $mapMagicAppInitialData)
        );
        array_map($pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(...), $javaScriptModules);
        $pageRenderer->addInlineSettingArray(null, $addInlineSettings);
        $pageRenderer->addInlineLanguageLabelFile('EXT:map_magic/Resources/Private/Language/locallang_mapMagic_failSafeErrorHandling_javascript.xlf');
        $stylesheets = $this->prototypeConfiguration['mapMagic']['stylesheets'];
        foreach ($stylesheets as $stylesheet) {
            $pageRenderer->addCssFile($stylesheet);
        }

        $moduleTemplate->setModuleClass($this->request->getPluginName() . '_' . $this->request->getControllerName());
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/locallang_module.xlf:mlang_tabs_tab'),
            $formDefinition['label']
        );
        return $moduleTemplate->renderResponse('Backend/MapEditor/Index');
    }

    /**
     * Initialize the save action.
     * This action uses the Fluid JsonView::class as view.
     */
    public function initializeSaveMapAction(): void
    {
        $this->defaultViewObjectName = JsonView::class;
    }

    /**
     * Save a formDefinition which was build by the form editor.
     */
    public function saveMapAction(string $formPersistenceIdentifier, MapDefinitionArray $formDefinition): ResponseInterface
    {
        $formDefinition = $formDefinition->getArrayCopy();

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeMapSave'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'beforeMapSave')) {
                $formDefinition = $hookObj->beforeMapSave(
                    $formPersistenceIdentifier,
                    $formDefinition
                );
            }
        }

        $response = [
            'status' => 'success',
        ];

        try {
            if (!$this->mapPersistenceManager->isAllowedPersistencePath($formPersistenceIdentifier)) {
                throw new PersistenceManagerException(sprintf('Save "%s" is not allowed', $formPersistenceIdentifier), 1614500663);
            }
            $this->mapPersistenceManager->save($formPersistenceIdentifier, $formDefinition);
            $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
            $this->prototypeConfiguration = $configurationService->getPrototypeConfiguration($formDefinition['prototypeName']);
            $formDefinition = $this->transformMapDefinitionForMapEditor($formDefinition);
            $response['formDefinition'] = $formDefinition;
        } catch (PersistenceManagerException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }

        $this->view->assign('response', $response);
        // saveMapAction uses the extbase JsonView::class.
        // That's why we have to set the view variables in this way.
        $this->view->setVariablesToRender([
            'response',
        ]);

        return $this->jsonResponse();
    }

    /**
     * Render a page from the formDefinition which was build by the form editor.
     * Use the frontend rendering and set the form framework to preview mode.
     */
    public function renderMapPageAction(
        MapDefinitionArray $formDefinition,
        int                $pageIndex,
        ?string            $prototypeName = null
    ): ResponseInterface {
        $prototypeName = $prototypeName ?: $formDefinition['prototypeName'] ?? 'standard';
        $formDefinition = $formDefinition->getArrayCopy();

        $formFactory = GeneralUtility::makeInstance(ArrayMapFactory::class);
        $formDefinition = $formFactory->build($formDefinition, $prototypeName);
        $formDefinition->setRenderingOption('previewMode', true);
        $form = $formDefinition->bind($this->request);
        $form->setCurrentSiteLanguage($this->buildFakeSiteLanguage(0, 0));
        $form->overrideCurrentPage($pageIndex);

        return $this->htmlResponse($form->render());
    }

    /**
     * Build a SiteLanguage object to render the form preview with a
     * specific language.
     */
    protected function buildFakeSiteLanguage(int $pageId, int $languageId): SiteLanguage
    {
        $fakeSiteConfiguration = [
            'languages' => [
                [
                    'languageId' => $languageId,
                    'title' => 'Dummy',
                    'navigationTitle' => '',
                    'flag' => '',
                    'locale' => '',
                ],
            ],
        ];

        $currentSiteLanguage = GeneralUtility::makeInstance(Site::class, 'map-dummy', $pageId, $fakeSiteConfiguration)
            ->getLanguageById($languageId);
        return $currentSiteLanguage;
    }

    /**
     * Prepare the mapElements.*.mapMagic section from the YAML settings.
     * Sort all mapElements into groups and add additional data.
     */
    protected function getInsertRenderablesPanelConfiguration(array $mapElementsDefinition): array
    {
        /** @var array<string, list<array<string, array{key: string, cssKey: string, label: string, sorting: int, iconIdentifier: string}>>> $mapElementsByGroup */
        $mapElementsByGroup = [];

        foreach ($mapElementsDefinition as $mapElementName => $mapElementConfiguration) {
            if (!isset($mapElementConfiguration['group'])) {
                continue;
            }
            if (!isset($mapElementsByGroup[$mapElementConfiguration['group']])) {
                $mapElementsByGroup[$mapElementConfiguration['group']] = [];
            }

            $mapElementConfiguration = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
                $mapElementConfiguration,
                $this->prototypeConfiguration['mapMagic']['translationFiles'] ?? []
            );

            $mapElementsByGroup[$mapElementConfiguration['group']][] = [
                'key' => $mapElementName,
                'cssKey' => preg_replace('/[^a-z0-9]/', '-', strtolower($mapElementName)),
                'label' => $mapElementConfiguration['label'],
                'sorting' => $mapElementConfiguration['groupSorting'],
                'iconIdentifier' => $mapElementConfiguration['iconIdentifier'],
            ];
        }

        $formGroups = [];
        foreach ($this->prototypeConfiguration['mapMagic']['mapElementGroups'] ?? [] as $groupName => $groupConfiguration) {
            if (!isset($mapElementsByGroup[$groupName])) {
                continue;
            }

            usort($mapElementsByGroup[$groupName], static function ($a, $b) {
                return $a['sorting'] - $b['sorting'];
            });

            $groupConfiguration = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
                $groupConfiguration,
                $this->prototypeConfiguration['mapMagic']['translationFiles'] ?? []
            );

            $formGroups[] = [
                'key' => $groupName,
                'elements' => $mapElementsByGroup[$groupName],
                'label' => $groupConfiguration['label'],
            ];
        }

        return $formGroups;
    }

    /**
     * Reduce the YAML settings by the 'mapMagic' keyword.
     */
    protected function getMapEditorDefinitions(): array
    {
        $mapMagicDefinitions = [];
        foreach ([$this->prototypeConfiguration, $this->prototypeConfiguration['mapMagic']] as $configuration) {
            foreach ($configuration as $firstLevelItemKey => $firstLevelItemValue) {
                if (substr($firstLevelItemKey, -10) !== 'Definition') {
                    continue;
                }
                $reducedKey = substr($firstLevelItemKey, 0, -10);
                foreach ($configuration[$firstLevelItemKey] as $mapMagicDefinitionKey => $mapMagicDefinitionValue) {
                    if (isset($mapMagicDefinitionValue['mapMagic'])) {
                        $mapMagicDefinitionValue = array_intersect_key($mapMagicDefinitionValue, array_flip(['mapMagic']));
                        $mapMagicDefinitions[$reducedKey][$mapMagicDefinitionKey] = $mapMagicDefinitionValue['mapMagic'];
                    } else {
                        $mapMagicDefinitions[$reducedKey][$mapMagicDefinitionKey] = $mapMagicDefinitionValue;
                    }
                }
            }
        }
        $mapMagicDefinitions = ArrayUtility::reIndexNumericArrayKeysRecursive($mapMagicDefinitions);
        $mapMagicDefinitions = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
            $mapMagicDefinitions,
            $this->prototypeConfiguration['mapMagic']['translationFiles'] ?? []
        );
        return $mapMagicDefinitions;
    }

    /**
     * Initialize ModuleTemplate and register docheader icons.
     */
    protected function initializeModuleTemplate(RequestInterface $request): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $getVars = $request->getArguments();

        if (isset($getVars['action']) && $getVars['action'] === 'index') {
            $newPageButton = $buttonBar->makeInputButton()
                ->setDataAttributes(['action' => 'formeditor-new-page', 'identifier' => 'headerNewPage'])
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:mapMagic.new_page_button'))
                ->setName('formeditor-new-page')
                ->setValue('new-page')
                ->setClasses('t3-mapmagic-element-new-page-button hidden')
                ->setIcon($this->iconFactory->getIcon('actions-page-new', Icon::SIZE_SMALL));

            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

            $closeButton = $buttonBar->makeLinkButton()
                ->setDataAttributes(['identifier' => 'closeButton'])
                ->setHref((string)$uriBuilder->buildUriFromRoute('web_MapMagicbuilder'))
                ->setClasses('t3-mapmagic-element-close-form-button hidden')
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:rm.closeDoc'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-close', Icon::SIZE_SMALL));

            $saveButton = $buttonBar->makeInputButton()
                ->setDataAttributes(['identifier' => 'saveButton'])
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:mapMagic.save_button'))
                ->setName('formeditor-save-form')
                ->setValue('save')
                ->setClasses('t3-mapmagic-element-save-form-button hidden')
                ->setIcon($this->iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL))
                ->setShowLabelText(true);

            $formSettingsButton = $buttonBar->makeInputButton()
                ->setDataAttributes(['identifier' => 'formSettingsButton'])
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:mapMagic.form_settings_button'))
                ->setName('formeditor-form-settings')
                ->setValue('settings')
                ->setClasses('t3-mapmagic-element-form-settings-button hidden')
                ->setIcon($this->iconFactory->getIcon('actions-system-extension-configure', Icon::SIZE_SMALL))
                ->setShowLabelText(true);

            $undoButton = $buttonBar->makeInputButton()
                ->setDataAttributes(['identifier' => 'undoButton'])
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:mapMagic.undo_button'))
                ->setName('formeditor-undo-form')
                ->setValue('undo')
                ->setClasses('t3-mapmagic-element-undo-form-button hidden disabled')
                ->setIcon($this->iconFactory->getIcon('actions-edit-undo', Icon::SIZE_SMALL));

            $redoButton = $buttonBar->makeInputButton()
                ->setDataAttributes(['identifier' => 'redoButton'])
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:map_magic/Resources/Private/Language/Database.xlf:mapMagic.redo_button'))
                ->setName('formeditor-redo-form')
                ->setValue('redo')
                ->setClasses('t3-mapmagic-element-redo-form-button hidden disabled')
                ->setIcon($this->iconFactory->getIcon('actions-edit-redo', Icon::SIZE_SMALL));

            $buttonBar->addButton($newPageButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
            $buttonBar->addButton($closeButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
            $buttonBar->addButton($saveButton, ButtonBar::BUTTON_POSITION_LEFT, 3);
            $buttonBar->addButton($formSettingsButton, ButtonBar::BUTTON_POSITION_LEFT, 4);
            $buttonBar->addButton($undoButton, ButtonBar::BUTTON_POSITION_LEFT, 5);
            $buttonBar->addButton($redoButton, ButtonBar::BUTTON_POSITION_LEFT, 5);
        }

        return $moduleTemplate;
    }

    /**
     * Render the form editor templates.
     */
    protected function renderMapEditorTemplates(array $mapMagicDefinitions): string
    {
        $fluidConfiguration = $this->prototypeConfiguration['mapMagic']['mapMagicFluidConfiguration'] ?? null;
        $mapMagicPartials = $this->prototypeConfiguration['mapMagic']['mapMagicPartials'] ?? null;

        if (!isset($fluidConfiguration['templatePathAndFilename'])) {
            throw new RenderingException(
                'The option templatePathAndFilename must be set.',
                1485636499
            );
        }
        if (
            !isset($fluidConfiguration['layoutRootPaths'])
            || !is_array($fluidConfiguration['layoutRootPaths'])
        ) {
            throw new RenderingException(
                'The option layoutRootPaths must be set.',
                1480294721
            );
        }
        if (
            !isset($fluidConfiguration['partialRootPaths'])
            || !is_array($fluidConfiguration['partialRootPaths'])
        ) {
            throw new RenderingException(
                'The option partialRootPaths must be set.',
                1480294722
            );
        }

        $insertRenderablesPanelConfiguration = $this->getInsertRenderablesPanelConfiguration($mapMagicDefinitions['mapElements']);

        $view = GeneralUtility::makeInstance(TemplateView::class);
        $view->getRenderingContext()->setRequest($this->request);
        $view->getRenderingContext()->getTemplatePaths()->fillFromConfigurationArray($fluidConfiguration);
        $view->setTemplatePathAndFilename($fluidConfiguration['templatePathAndFilename']);
        $view->assignMultiple([
            'insertRenderablesPanelConfiguration' => $insertRenderablesPanelConfiguration,
            'mapMagicPartials' => $mapMagicPartials,
        ]);

        return $view->render();
    }

    /**
     * @todo move this to MapDefinitionConversionService
     */
    protected function transformMapDefinitionForMapEditor(array $formDefinition): array
    {
        /** @var array<string, list<string>> $multiValueMapElementProperties */
        $multiValueMapElementProperties = [];
        /** @var array<string, list<string>> $multiValueFinisherProperties */
        $multiValueFinisherProperties = [];

        foreach ($this->prototypeConfiguration['mapElementsDefinition'] as $type => $configuration) {
            if (!isset($configuration['mapMagic']['editors'])) {
                continue;
            }
            foreach ($configuration['mapMagic']['editors'] as $editorConfiguration) {
                if (($editorConfiguration['templateName'] ?? '') === 'Inspector-PropertyGridEditor') {
                    $multiValueMapElementProperties[$type][] = $editorConfiguration['propertyPath'];
                }
            }
        }

        foreach ($this->prototypeConfiguration['mapElementsDefinition']['MapMagic']['mapMagic']['propertyCollections']['finishers'] ?? [] as $configuration) {
            if (!isset($configuration['editors'])) {
                continue;
            }

            foreach ($configuration['editors'] as $editorConfiguration) {
                if (($editorConfiguration['templateName'] ?? '') === 'Inspector-PropertyGridEditor') {
                    $multiValueFinisherProperties[$configuration['identifier']][] = $editorConfiguration['propertyPath'];
                }
            }
        }

        $formDefinition = $this->filterEmptyArrays($formDefinition);
        $formDefinition = $this->migrateEmailFinisherRecipients($formDefinition);

        // @todo: replace with rte parsing
        $formDefinition = ArrayUtility::stripTagsFromValuesRecursive($formDefinition);
        $formDefinition = $this->transformMultiValuePropertiesForMapEditor(
            $formDefinition,
            'type',
            $multiValueMapElementProperties
        );
        $formDefinition = $this->transformMultiValuePropertiesForMapEditor(
            $formDefinition,
            'identifier',
            $multiValueFinisherProperties
        );

        $formDefinition = $this->formDefinitionConversionService->addHmacData($formDefinition);
        $formDefinition = $this->formDefinitionConversionService->migrateFinisherConfiguration($formDefinition);

        return $formDefinition;
    }

    /**
     * Some data needs a transformation before it can be used by the
     * form editor. This rules for multivalue elements like select
     * elements. To ensure the right sorting if the data goes into
     * javascript, we need to do transformations:
     *
     * [
     *   '5' => '5',
     *   '4' => '4',
     *   '3' => '3'
     * ]
     *
     *
     * This method transform this into:
     *
     * [
     *   [
     *     _label => '5'
     *     _value => 5
     *   ],
     *   [
     *     _label => '4'
     *     _value => 4
     *   ],
     *   [
     *     _label => '3'
     *     _value => 3
     *   ],
     * ]
     *
     * @param array<string, list<string>> $multiValueProperties
     */
    protected function transformMultiValuePropertiesForMapEditor(
        array $formDefinition,
        string $identifierProperty,
        array $multiValueProperties
    ): array {
        $output = $formDefinition;
        foreach ($formDefinition as $key => $value) {
            $identifier = $value[$identifierProperty] ?? null;

            if (is_string($identifier) && array_key_exists($identifier, $multiValueProperties)) {
                $multiValuePropertiesForIdentifier = $multiValueProperties[$identifier];

                foreach ($multiValuePropertiesForIdentifier as $multiValueProperty) {
                    if (!ArrayUtility::isValidPath($value, $multiValueProperty, '.')) {
                        continue;
                    }

                    $multiValuePropertyData = ArrayUtility::getValueByPath($value, $multiValueProperty, '.');

                    if (!is_array($multiValuePropertyData)) {
                        continue;
                    }

                    $newMultiValuePropertyData = [];

                    foreach ($multiValuePropertyData as $k => $v) {
                        $newMultiValuePropertyData[] = [
                            '_label' => $v,
                            '_value' => $k,
                        ];
                    }

                    $value = ArrayUtility::setValueByPath($value, $multiValueProperty, $newMultiValuePropertyData, '.');
                }
            }

            $output[$key] = $value;

            if (is_array($value)) {
                $output[$key] = $this->transformMultiValuePropertiesForMapEditor(
                    $value,
                    $identifierProperty,
                    $multiValueProperties
                );
            }
        }

        return $output;
    }

    /**
     * Remove keys from an array if the key value is an empty array
     */
    protected function filterEmptyArrays(array $array): array
    {
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (empty($value)) {
                unset($array[$key]);
                continue;
            }
            $array[$key] = $this->filterEmptyArrays($value);
            if (empty($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Migrate single recipient options to their list successors
     */
    protected function migrateEmailFinisherRecipients(array $formDefinition): array
    {
        foreach ($formDefinition['finishers'] ?? [] as $i => $finisherConfiguration) {
            if (!in_array($finisherConfiguration['identifier'], ['EmailToSender', 'EmailToReceiver'], true)) {
                continue;
            }

            $recipientAddress = $finisherConfiguration['options']['recipientAddress'] ?? '';
            $recipientName = $finisherConfiguration['options']['recipientName'] ?? '';
            $carbonCopyAddress = $finisherConfiguration['options']['carbonCopyAddress'] ?? '';
            $blindCarbonCopyAddress = $finisherConfiguration['options']['blindCarbonCopyAddress'] ?? '';
            $replyToAddress = $finisherConfiguration['options']['replyToAddress'] ?? '';

            if (!empty($recipientAddress)) {
                $finisherConfiguration['options']['recipients'][$recipientAddress] = $recipientName;
            }

            if (!empty($carbonCopyAddress)) {
                $finisherConfiguration['options']['carbonCopyRecipients'][$carbonCopyAddress] = '';
            }

            if (!empty($blindCarbonCopyAddress)) {
                $finisherConfiguration['options']['blindCarbonCopyRecipients'][$blindCarbonCopyAddress] = '';
            }

            if (!empty($replyToAddress)) {
                $finisherConfiguration['options']['replyToRecipients'][$replyToAddress] = '';
            }

            unset(
                $finisherConfiguration['options']['recipientAddress'],
                $finisherConfiguration['options']['recipientName'],
                $finisherConfiguration['options']['carbonCopyAddress'],
                $finisherConfiguration['options']['blindCarbonCopyAddress'],
                $finisherConfiguration['options']['replyToAddress']
            );
            $formDefinition['finishers'][$i] = $finisherConfiguration;
        }

        return $formDefinition;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
