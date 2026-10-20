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
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use WapplerSystems\MapMagic\Exception as MapException;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;
use WapplerSystems\MapMagic\Service\DatabaseService;
use WapplerSystems\MapMagic\Service\TranslationService;

/**
 * The form manager controller
 *
 * Scope: backend
 * @internal
 */
class MapManagerController extends AbstractBackendController
{
    protected const JS_MODULE_NAMES = ['app', 'viewModel'];

    protected int $limit = 20;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly IconFactory $iconFactory,
        // @todo: unit tests set next one, so no readonly. refactor them to functionals?!
        protected DatabaseService $databaseService,
    ) {}

    /**
     * Display the Map Manager. The main showing available forms.
     */
    protected function indexAction(int $page = 1): ResponseInterface
    {
        $forms = $this->getAvailableMapDefinitions();
        $arrayPaginator = new ArrayPaginator($forms, $page, $this->limit);
        $pagination = new SimplePagination($arrayPaginator);

        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple([
            'paginator' => $arrayPaginator,
            'pagination' => $pagination,
            'stylesheets' => $this->mapSettings['mapManager']['stylesheets'],
            'mapManagerAppInitialData' => json_encode($this->getMapManagerAppInitialData()),
        ]);
        if (!empty($this->mapSettings['mapManager']['javaScriptTranslationFile'])) {
            $this->pageRenderer->addInlineLanguageLabelFile($this->mapSettings['mapManager']['javaScriptTranslationFile']);
        }

        $javaScriptModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::create($name),
            array_filter(
                $this->mapSettings['mapManager']['dynamicJavaScriptModules'] ?? [],
                fn(string $name) => in_array($name, self::JS_MODULE_NAMES, true),
                ARRAY_FILTER_USE_KEY
            )
        );
        $requireJsModules = array_map(
            static fn(string $name) => JavaScriptModuleInstruction::forRequireJS($name),
            array_filter(
                $this->mapSettings['mapManager']['dynamicRequireJsModules'] ?? [],
                fn(string $name) => in_array($name, self::JS_MODULE_NAMES, true),
                ARRAY_FILTER_USE_KEY
            )
        );

        $jsModules = $requireJsModules + $javaScriptModules;
        if (count($requireJsModules)) {
            trigger_error(
                'mapManager.dynamicRequireJsModules has been deprecated in v12 and will be removed with v13. ' .
                'Use mapManager.dynamicJavaScriptModules instead.',
                E_USER_DEPRECATED
            );
            $this->pageRenderer->loadRequireJs();
        }
        $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@wapplersystems/mapmagic/backend/helper.js', 'Helper')
                ->invoke('dispatchMapManager', $jsModules, $this->getMapManagerAppInitialData())
        );
        array_map($this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(...), $javaScriptModules);
        $moduleTemplate->setModuleClass($this->request->getPluginName() . '_' . $this->request->getControllerName());
        $moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('map_magic.module:mlang_tabs_tab')
        );
        return $moduleTemplate->renderResponse('Backend/MapManager/Index');
    }

    /**
     * Initialize the "create" action.
     * This action uses the Fluid JsonView::class as view.
     */
    protected function initializeCreateAction(): void
    {
        $this->defaultViewObjectName = JsonView::class;
    }

    /**
     * Creates a new Map and redirects to the Map Editor
     *
     * @throws MapException
     * @throws PersistenceManagerException
     */
    protected function createAction(string $mapName, string $templatePath, string $prototypeName, string $savePath): ResponseInterface
    {
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($savePath)) {
            throw new PersistenceManagerException(sprintf('Save to path "%s" is not allowed', $savePath), 1614500657);
        }

        if (!$this->isValidTemplatePath($prototypeName, $templatePath)) {
            throw new MapException(sprintf('The template path "%s" is not allowed', $templatePath), 1329233410);
        }
        if (empty($mapName)) {
            throw new MapException('No map name', 1472312204);
        }

        $templatePath = GeneralUtility::getFileAbsFileName($templatePath);
        $form = Yaml::parse((string)file_get_contents($templatePath));
        $form['label'] = $mapName;
        $form['identifier'] = $this->mapPersistenceManager->getUniqueIdentifier($this->convertMapNameToIdentifier($mapName));
        $form['prototypeName'] = $prototypeName;

        $formPersistenceIdentifier = $this->mapPersistenceManager->getUniquePersistenceIdentifier($form['identifier'], $savePath);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeMapCreate'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'beforeMapCreate')) {
                $form = $hookObj->beforeMapCreate(
                    $formPersistenceIdentifier,
                    $form
                );
            }
        }

        $response = [
            'status' => 'success',
            'url' => $this->uriBuilder->uriFor('index', ['formPersistenceIdentifier' => $formPersistenceIdentifier], 'MapEditor'),
        ];

        $form = ArrayUtility::stripTagsFromValuesRecursive($form);
        try {
            $this->mapPersistenceManager->save($formPersistenceIdentifier, $form);
        } catch (PersistenceManagerException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }

        $this->view->assign('response', $response);
        // createAction uses the Extbase JsonView::class.
        // That's why we have to set the view variables in this way.
        $this->view->setVariablesToRender([
            'response',
        ]);

        return $this->jsonResponse();
    }

    /**
     * Initialize the duplicate action.
     * This action uses the Fluid JsonView::class as view.
     */
    protected function initializeDuplicateAction(): void
    {
        $this->defaultViewObjectName = JsonView::class;
    }

    /**
     * Duplicates a given formDefinition and redirects to the Map Editor
     *
     * @throws PersistenceManagerException
     */
    protected function duplicateAction(string $formName, string $formPersistenceIdentifier, string $savePath): ResponseInterface
    {
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($savePath)) {
            throw new PersistenceManagerException(sprintf('Save to path "%s" is not allowed', $savePath), 1614500658);
        }
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($formPersistenceIdentifier)) {
            throw new PersistenceManagerException(sprintf('Read of "%s" is not allowed', $formPersistenceIdentifier), 1614500659);
        }

        $formToDuplicate = $this->mapPersistenceManager->load($formPersistenceIdentifier);
        $formToDuplicate['label'] = $formName;
        $formToDuplicate['identifier'] = $this->mapPersistenceManager->getUniqueIdentifier($this->convertMapNameToIdentifier($formName));

        $formPersistenceIdentifier = $this->mapPersistenceManager->getUniquePersistenceIdentifier($formToDuplicate['identifier'], $savePath);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeMapDuplicate'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'beforeMapDuplicate')) {
                $formToDuplicate = $hookObj->beforeMapDuplicate(
                    $formPersistenceIdentifier,
                    $formToDuplicate
                );
            }
        }

        $response = [
            'status' => 'success',
            'url' => $this->uriBuilder->uriFor('index', ['formPersistenceIdentifier' => $formPersistenceIdentifier], 'MapEditor'),
        ];

        $formToDuplicate = ArrayUtility::stripTagsFromValuesRecursive($formToDuplicate);
        try {
            $this->mapPersistenceManager->save($formPersistenceIdentifier, $formToDuplicate);
        } catch (PersistenceManagerException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }

        $this->view->assign('response', $response);
        // createAction uses the Extbase JsonView::class.
        // That's why we have to set the view variables in this way.
        $this->view->setVariablesToRender([
            'response',
        ]);

        return $this->jsonResponse();
    }

    /**
     * Initialize the references action.
     * This action uses the Fluid JsonView::class as view.
     */
    protected function initializeReferencesAction(): void
    {
        $this->defaultViewObjectName = JsonView::class;
    }

    /**
     * Show references to this persistence identifier
     *
     * @throws PersistenceManagerException
     */
    protected function referencesAction(string $formPersistenceIdentifier): ResponseInterface
    {
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($formPersistenceIdentifier)) {
            throw new PersistenceManagerException(sprintf('Read from "%s" is not allowed', $formPersistenceIdentifier), 1614500660);
        }

        $this->view->assign('references', $this->getProcessedReferencesRows($formPersistenceIdentifier));
        $this->view->assign('formPersistenceIdentifier', $formPersistenceIdentifier);
        // referencesAction uses the extbase JsonView::class.
        // That's why we have to set the view variables in this way.
        $this->view->setVariablesToRender([
            'references',
            'formPersistenceIdentifier',
        ]);

        return $this->jsonResponse();
    }

    /**
     * Delete a formDefinition identified by the $formPersistenceIdentifier.
     *
     * @throws PersistenceManagerException
     */
    protected function deleteAction(string $formPersistenceIdentifier): ResponseInterface
    {
        if (!$this->mapPersistenceManager->isAllowedPersistencePath($formPersistenceIdentifier)) {
            throw new PersistenceManagerException(sprintf('Delete "%s" is not allowed', $formPersistenceIdentifier), 1614500661);
        }

        if (empty($this->databaseService->getReferencesByPersistenceIdentifier($formPersistenceIdentifier))) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeMapDelete'] ?? [] as $className) {
                $hookObj = GeneralUtility::makeInstance($className);
                if (method_exists($hookObj, 'beforeMapDelete')) {
                    $hookObj->beforeMapDelete(
                        $formPersistenceIdentifier
                    );
                }
            }

            $this->mapPersistenceManager->delete($formPersistenceIdentifier);
        } else {
            $controllerConfiguration = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
                $this->mapSettings['mapManager']['controller'],
                $this->mapSettings['mapManager']['translationFiles'] ?? []
            );

            $this->addFlashMessage(
                sprintf($controllerConfiguration['deleteAction']['errorMessage'], $formPersistenceIdentifier),
                $controllerConfiguration['deleteAction']['errorTitle'],
                ContextualFeedbackSeverity::ERROR,
                true
            );
        }
        return $this->redirect('index');
    }

    /**
     * Return a list of all accessible file mountpoints.
     *
     * Only registered mount points from
     * persistenceManager.allowedFileMounts
     * are listed. This list will be reduced by the configured
     * mount points for the current backend user.
     */
    protected function getAccessibleMapStorageFolders(): array
    {
        $preparedAccessibleMapStorageFolders = [];
        foreach ($this->mapPersistenceManager->getAccessibleMapStorageFolders() as $identifier => $folder) {
            $preparedAccessibleMapStorageFolders[] = [
                'label' => $folder->getStorage()->isPublic() ? $folder->getPublicUrl() : $identifier,
                'value' => $identifier,
            ];
        }

        if ($this->mapSettings['persistenceManager']['allowSaveToExtensionPaths']) {
            foreach ($this->mapPersistenceManager->getAccessibleExtensionFolders() as $relativePath => $fullPath) {
                $preparedAccessibleMapStorageFolders[] = [
                    'label' => $relativePath,
                    'value' => $relativePath,
                ];
            }
        }

        return $preparedAccessibleMapStorageFolders;
    }

    /**
     * Returns the json encoded data which is used by the form editor
     * JavaScript app.
     */
    protected function getMapManagerAppInitialData(): array
    {
        $mapManagerAppInitialData = [
            'selectablePrototypesConfiguration' => $this->mapSettings['mapManager']['selectablePrototypesConfiguration'],
            'accessibleMapStorageFolders' => $this->getAccessibleMapStorageFolders(),
            'endpoints' => [
                'create' => $this->uriBuilder->uriFor('create'),
                'duplicate' => $this->uriBuilder->uriFor('duplicate'),
                'delete' => $this->uriBuilder->uriFor('delete'),
                'references' => $this->uriBuilder->uriFor('references'),
            ],
        ];

        $mapManagerAppInitialData = ArrayUtility::reIndexNumericArrayKeysRecursive($mapManagerAppInitialData);
        $mapManagerAppInitialData = GeneralUtility::makeInstance(TranslationService::class)->translateValuesRecursive(
            $mapManagerAppInitialData,
            $this->mapSettings['mapManager']['translationFiles'] ?? []
        );
        return $mapManagerAppInitialData;
    }

    /**
     * List all formDefinitions which can be loaded through t form persistence
     * manager. Enrich this data by a reference counter.
     */
    protected function getAvailableMapDefinitions(): array
    {
        $allReferencesForFileUid = $this->databaseService->getAllReferencesForFileUid();
        $allReferencesForPersistenceIdentifier = $this->databaseService->getAllReferencesForPersistenceIdentifier();

        $availableMapDefinitions = [];
        foreach ($this->mapPersistenceManager->listMaps() as $formDefinition) {
            $referenceCount  = 0;
            if (
                isset($formDefinition['fileUid'])
                && array_key_exists($formDefinition['fileUid'], $allReferencesForFileUid)
            ) {
                $referenceCount = $allReferencesForFileUid[$formDefinition['fileUid']];
            } elseif (array_key_exists($formDefinition['persistenceIdentifier'], $allReferencesForPersistenceIdentifier)) {
                $referenceCount = $allReferencesForPersistenceIdentifier[$formDefinition['persistenceIdentifier']];
            }

            $formDefinition['referenceCount'] = $referenceCount;
            $availableMapDefinitions[] = $formDefinition;
        }

        return $availableMapDefinitions;
    }

    /**
     * Returns an array with information about the references for a
     * formDefinition identified by $persistenceIdentifier.
     *
     * @throws \InvalidArgumentException
     */
    protected function getProcessedReferencesRows(string $persistenceIdentifier): array
    {
        if (empty($persistenceIdentifier)) {
            throw new \InvalidArgumentException('$persistenceIdentifier must not be empty.', 1477071939);
        }

        $references = [];
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $referenceRows = $this->databaseService->getReferencesByPersistenceIdentifier($persistenceIdentifier);
        foreach ($referenceRows as &$referenceRow) {
            $record = $this->getRecord($referenceRow['tablename'], $referenceRow['recuid']);
            if (!$record) {
                continue;
            }
            $pageRecord = $this->getRecord('pages', $record['pid']);
            $urlParameters = [
                'edit' => [
                    $referenceRow['tablename'] => [
                        $referenceRow['recuid'] => 'edit',
                    ],
                ],
                'returnUrl' => $this->getModuleUrl('web_MapMagicbuilder'),
            ];

            $references[] = [
                'recordPageTitle' => is_array($pageRecord) ? $this->getRecordTitle('pages', $pageRecord) : '',
                'recordTitle' => $this->getRecordTitle($referenceRow['tablename'], $record, true),
                'recordIcon' => $iconFactory->getIconForRecord($referenceRow['tablename'], $record, \TYPO3\CMS\Core\Imaging\IconSize::SMALL)->render(),
                'recordUid' => $referenceRow['recuid'],
                'recordEditUrl' => $this->getModuleUrl('record_edit', $urlParameters),
            ];
        }
        return $references;
    }

    /**
     * Check if a given $templatePath for a given $prototypeName is valid
     * and accessible.
     *
     * Valid template paths has to be configured within
     * mapManager.selectablePrototypesConfiguration.[('identifier':  $prototypeName)].newMapTemplates.[('templatePath': $templatePath)]
     */
    protected function isValidTemplatePath(string $prototypeName, string $templatePath): bool
    {
        $isValid = false;
        foreach ($this->mapSettings['mapManager']['selectablePrototypesConfiguration'] as $prototypesConfiguration) {
            if ($prototypesConfiguration['identifier'] !== $prototypeName) {
                continue;
            }
            foreach ($prototypesConfiguration['newMapTemplates'] as $templatesConfiguration) {
                if ($templatesConfiguration['templatePath'] !== $templatePath) {
                    continue;
                }
                $isValid = true;
                break;
            }
        }

        $templatePath = GeneralUtility::getFileAbsFileName($templatePath);
        if (!is_file($templatePath)) {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Init ModuleTemplate and register document header buttons
     */
    protected function initializeModuleTemplate(ServerRequestInterface $request): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Create new
        $addMapButton = $buttonBar->makeLinkButton()
            ->setDataAttributes(['identifier' => 'newMap'])
            ->setHref('#')
            ->setTitle($this->getLanguageService()->sL('map_magic.database:mapManager.create_new_form'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-plus', \TYPO3\CMS\Core\Imaging\IconSize::SMALL));
        $buttonBar->addButton($addMapButton, ButtonBar::BUTTON_POSITION_LEFT);

        // Reload
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($this->request->getAttribute('normalizedParams')->getRequestUri())
            ->setTitle($this->getLanguageService()->sL('core.core:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', \TYPO3\CMS\Core\Imaging\IconSize::SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);

        // Shortcut
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('web_MapMagicbuilder')
            ->setDisplayName($this->getLanguageService()->sL('map_magic.database:module.shortcut_name'));
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);

        return $moduleTemplate;
    }

    /**
     * Returns a form identifier which is the lower cased form name.
     */
    protected function convertMapNameToIdentifier(string $formName): string
    {
        $csConverter = GeneralUtility::makeInstance(CharsetConverter::class);

        $formName = \Normalizer::normalize($formName) ?: $formName;
        $formIdentifier = $csConverter->specCharsToASCII('utf-8', $formName);
        $formIdentifier = (string)preg_replace('/[^a-zA-Z0-9-_]/', '', $formIdentifier);
        $formIdentifier = lcfirst($formIdentifier);
        return $formIdentifier;
    }

    /**
     * Wrapper used for unit testing.
     */
    protected function getRecord(string $table, int $uid): ?array
    {
        return BackendUtility::getRecord($table, $uid);
    }

    /**
     * Wrapper used for unit testing.
     */
    protected function getRecordTitle(string $table, array $row, bool $prep = false): string
    {
        return BackendUtility::getRecordTitle($table, $row, $prep);
    }

    /**
     * Wrapper used for unit testing.
     */
    protected function getModuleUrl(string $moduleName, array $urlParameters = []): string
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute($moduleName, $urlParameters);
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
