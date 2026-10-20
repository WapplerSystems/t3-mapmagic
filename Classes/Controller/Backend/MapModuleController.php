<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use WapplerSystems\MapMagic\Backend\MapDefinitionService;
use WapplerSystems\MapMagic\Frontend\BasemapPresets;
use WapplerSystems\MapMagic\Frontend\RendererMessages;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;

/**
 * The "Maps" module: list of map definitions and the editor page. Both are
 * thin; the list actions and the editor talk to MapAjaxController.
 */
#[AsController]
final readonly class MapModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private MapDefinitionService $maps,
        private BasemapPresets $basemapPresets,
        private PageRenderer $pageRenderer,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->label('map_magic.module:title'));
        $this->pageRenderer->loadJavaScriptModule('@wapplersystems/mapmagic/backend/map-list.js');
        $this->pageRenderer->addInlineLanguageDomain('map_magic.editor');

        $maps = array_map(fn(array $map): array => $map + [
            'editUrl' => (string)$this->uriBuilder->buildUriFromRoute('content_mapmagic.edit', ['map' => $map['persistenceIdentifier']]),
        ], $this->maps->listMaps());

        $folders = $this->maps->storageFolders();
        if ($folders !== []) {
            $view->getDocHeaderComponent()->getButtonBar()->addButton(
                $view->getDocHeaderComponent()->getButtonBar()->makeLinkButton()
                    ->setHref('#')
                    ->setDataAttributes(['mapmagic-action' => 'create'])
                    ->setTitle($this->label('map_magic.editor:list.create'))
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-add', IconSize::SMALL)),
                ButtonBar::BUTTON_POSITION_LEFT
            );
        }

        return $view->assignMultiple([
            'maps' => $maps,
            'folders' => $folders,
            'templates' => MapDefinitionService::TEMPLATES,
        ])->renderResponse('Backend/Index');
    }

    public function editAction(ServerRequestInterface $request): ResponseInterface
    {
        $persistenceIdentifier = (string)($request->getQueryParams()['map'] ?? '');
        $listUrl = (string)$this->uriBuilder->buildUriFromRoute('content_mapmagic');
        try {
            $map = $this->maps->accessibleMap($persistenceIdentifier);
            $definition = $this->maps->load($persistenceIdentifier);
        } catch (PersistenceManagerException) {
            return new RedirectResponse($listUrl);
        }

        // The editor reuses the frontend renderer for the live preview, plus
        // Geoman for drawing. All are classic scripts that set globals (L, MapMagic).
        foreach (['Leaflet/leaflet.css', 'Leaflet/geoman/leaflet-geoman.css', 'Frontend/mapmagic.css', 'Css/editor.css'] as $css) {
            $this->pageRenderer->addCssFile('EXT:map_magic/Resources/Public/' . $css);
        }
        foreach (['Leaflet/leaflet.js', 'Leaflet/geoman/leaflet-geoman.min.js', 'Frontend/mapmagic.js'] as $js) {
            $this->pageRenderer->addJsFile('EXT:map_magic/Resources/Public/' . $js);
        }
        $this->pageRenderer->loadJavaScriptModule('@wapplersystems/mapmagic/backend/editor/app.js');
        $this->pageRenderer->addInlineLanguageDomain('map_magic.editor');

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->label('map_magic.module:title'), (string)($definition['label'] ?? $map['name']));
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setHref($listUrl)
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.goBack'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL)),
            ButtonBar::BUTTON_POSITION_LEFT
        );

        return $view->assignMultiple([
            'map' => $map,
            'editorConfig' => [
                'persistenceIdentifier' => $persistenceIdentifier,
                'readOnly' => $map['readOnly'] || $map['invalid'],
                'listUrl' => $listUrl,
                'basemapPresets' => $this->basemapPresets->all(),
                'messages' => RendererMessages::for($request),
                'definition' => $definition,
            ],
        ])->renderResponse('Backend/Edit');
    }

    private function label(string $key): string
    {
        return $this->getLanguageService()->sL($key) ?: $key;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
