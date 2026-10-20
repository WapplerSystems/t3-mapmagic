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
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use WapplerSystems\MapMagic\Frontend\MapCacheTag;
use WapplerSystems\MapMagic\Frontend\MapDefinitionCompiler;
use WapplerSystems\MapMagic\Frontend\RendererMessages;
use WapplerSystems\MapMagic\Mvc\Persistence\MapPersistenceManagerInterface;

/**
 * Renders the map selected in the plugin's FlexForm. The page only gets a
 * container with the map definition as data attribute; the renderer script
 * picks it up, so there is no inline JavaScript (CSP).
 */
class MapFrontendController extends ActionController
{

    public function __construct(
        private readonly MapPersistenceManagerInterface $mapPersistenceManager,
        private readonly MapDefinitionCompiler $compiler,
        private readonly LoggerInterface $logger,
    ) {}

    public function renderAction(): ResponseInterface
    {
        $persistenceIdentifier = (string)($this->settings['persistenceIdentifier'] ?? '');
        if ($persistenceIdentifier === '') {
            return $this->htmlResponse('');
        }

        $this->request->getAttribute('frontend.cache.collector')?->addCacheTags(
            new CacheTag(MapCacheTag::for($persistenceIdentifier))
        );
        $contentUid = (int)($this->request->getAttribute('currentContentObject')?->data['uid'] ?? 0);
        try {
            $definition = $this->mapPersistenceManager->load($persistenceIdentifier);
            if (!empty($definition['invalid'])) {
                throw new \RuntimeException((string)($definition['label'] ?? 'invalid map definition'), 1792460010);
            }
            // The content uid makes the id unique when one map is placed twice on a page.
            $map = $this->compiler->compile($definition, 'mapmagic-' . $contentUid, $this->request);
        } catch (\Throwable $e) {
            $this->logger->error('Map "{map}" in content element {uid} not rendered: {message}', [
                'map' => $persistenceIdentifier,
                'uid' => $contentUid,
                'message' => $e->getMessage(),
            ]);
            return $this->htmlResponse('');
        }

        $this->view->assignMultiple([
            'map' => $map,
            'mapJson' => json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'messagesJson' => json_encode(RendererMessages::for($this->request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        return $this->htmlResponse();
    }

}
