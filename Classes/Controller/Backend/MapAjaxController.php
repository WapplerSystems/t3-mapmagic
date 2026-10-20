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
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\JsonResponse;
use WapplerSystems\MapMagic\Backend\MapDefinitionService;
use WapplerSystems\MapMagic\Frontend\MapCacheTag;
use WapplerSystems\MapMagic\Frontend\MapDefinitionCompiler;
use WapplerSystems\MapMagic\Mvc\Configuration\YamlSource;
use WapplerSystems\MapMagic\Mvc\Persistence\Exception\PersistenceManagerException;

/**
 * JSON endpoints of the map editor and the map list. Requests carry a JSON
 * body (TYPO3 does not parse those into getParsedBody()). Errors are
 * returned as `{error: message}` with a 4xx status, which the JavaScript
 * side shows as notification.
 */
#[AsController]
final readonly class MapAjaxController
{
    public function __construct(
        private MapDefinitionService $maps,
        private MapDefinitionCompiler $compiler,
        private CacheManager $cacheManager,
        private UriBuilder $uriBuilder,
        private LoggerInterface $logger,
    ) {}

    /**
     * The preview cannot render the file format directly: EXT:/FAL paths,
     * presets and record sources only resolve on the server.
     */
    public function previewAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(function () use ($request): array {
            $definition = $this->definition($this->body($request));
            return $this->compiler->compileWithReport($definition, 'mapmagic-preview', $request);
        });
    }

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(function () use ($request): array {
            $body = $this->body($request);
            $persistenceIdentifier = (string)($body['persistenceIdentifier'] ?? '');
            $this->maps->save($persistenceIdentifier, $this->definition($body));
            // The plugin is cached; without this the change shows up only after a full cache flush.
            $this->cacheManager->flushCachesInGroupByTag('pages', MapCacheTag::for($persistenceIdentifier));
            return ['saved' => true];
        });
    }

    public function yamlDumpAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(fn(): array => [
            // Same settings as the persistence layer, so the YAML view shows what lands in the file.
            'yaml' => Yaml::dump($this->definition($this->body($request)), YamlSource::INLINE_LEVEL, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE),
        ]);
    }

    public function yamlParseAction(ServerRequestInterface $request): ResponseInterface
    {
        $yaml = (string)($this->body($request)['yaml'] ?? '');
        try {
            $definition = Yaml::parse($yaml);
        } catch (ParseException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'line' => $e->getParsedLine()], 422);
        }
        if (!is_array($definition)) {
            return new JsonResponse(['error' => 'The YAML must describe a map (a mapping at the top level).', 'line' => 1], 422);
        }
        return new JsonResponse(['definition' => $definition]);
    }

    public function createAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(function () use ($request): array {
            $body = $this->body($request);
            $persistenceIdentifier = $this->maps->create(
                (string)($body['label'] ?? ''),
                (string)($body['template'] ?? 'blank'),
                (string)($body['folder'] ?? '')
            );
            return ['persistenceIdentifier' => $persistenceIdentifier, 'editUrl' => $this->editUrl($persistenceIdentifier)];
        });
    }

    public function duplicateAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(function () use ($request): array {
            $body = $this->body($request);
            $persistenceIdentifier = $this->maps->duplicate(
                (string)($body['persistenceIdentifier'] ?? ''),
                (string)($body['label'] ?? ''),
                (string)($body['folder'] ?? '')
            );
            return ['persistenceIdentifier' => $persistenceIdentifier, 'editUrl' => $this->editUrl($persistenceIdentifier)];
        });
    }

    public function deleteAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle(function () use ($request): array {
            $persistenceIdentifier = (string)($this->body($request)['persistenceIdentifier'] ?? '');
            $this->maps->delete($persistenceIdentifier);
            $this->cacheManager->flushCachesInGroupByTag('pages', MapCacheTag::for($persistenceIdentifier));
            return ['deleted' => true];
        });
    }

    /**
     * @param \Closure(): array<string, mixed> $action
     */
    private function handle(\Closure $action): ResponseInterface
    {
        try {
            return new JsonResponse($action());
        } catch (PersistenceManagerException|\InvalidArgumentException|\JsonException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logger->error('MapMagic editor request failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ServerRequestInterface $request): array
    {
        $body = json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            throw new \InvalidArgumentException('Expected a JSON object', 1792460030);
        }
        return $body;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function definition(array $body): array
    {
        $definition = $body['definition'] ?? null;
        if (!is_array($definition)) {
            throw new \InvalidArgumentException('Missing map definition', 1792460031);
        }
        return $definition;
    }

    private function editUrl(string $persistenceIdentifier): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('content_mapmagic.edit', ['map' => $persistenceIdentifier]);
    }
}
