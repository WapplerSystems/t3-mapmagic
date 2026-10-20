<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\TypoScriptService as CoreTypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Utilities to manage and convert TypoScript
 *
 * Scope: frontend
 */
class TypoScriptService
{
    protected CoreTypoScriptService $coreTypoScriptService;

    public function __construct(CoreTypoScriptService $coreTypoScriptService)
    {
        $this->coreTypoScriptService = $coreTypoScriptService;
    }

    /**
     * Parse a configuration with ContentObjectRenderer::cObjGetSingle()
     * and return the result.
     *
     * @internal
     */
    public function resolvePossibleTypoScriptConfiguration(array $configuration, ServerRequestInterface $request): array
    {
        $configuration = $this->coreTypoScriptService->convertPlainArrayToTypoScriptArray($configuration);
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObjectRenderer->setRequest($request);
        $contentObjectRenderer->start($request->getAttribute('frontend.page.information')->getPageRecord(), 'pages');
        $configuration = $this->resolveTypoScriptConfiguration($configuration, $contentObjectRenderer);
        $configuration = $this->coreTypoScriptService->convertTypoScriptArrayToPlainArray($configuration);
        return $configuration;
    }

    /**
     * Parse a configuration with ContentObjectRenderer::cObjGetSingle()
     * if there is an array key without and with a dot at the end.
     * This sample would be identified as a TypoScript parsable configuration
     * part:
     *
     * [
     *   'example' => 'TEXT'
     *   'example.' => [
     *     'value' => 'some value'
     *   ]
     * ]
     *
     */
    protected function resolveTypoScriptConfiguration(array $configuration, ContentObjectRenderer $contentObjectRenderer): array
    {
        foreach ($configuration as $key => $value) {
            $keyWithoutDot = rtrim((string)$key, '.');
            if (isset($configuration[$keyWithoutDot]) && isset($configuration[$keyWithoutDot . '.'])) {
                $value = $contentObjectRenderer->cObjGetSingle(
                    $configuration[$keyWithoutDot],
                    $configuration[$keyWithoutDot . '.'],
                    $keyWithoutDot
                );
                $configuration[$keyWithoutDot] = $value;
            } elseif (!isset($configuration[$keyWithoutDot]) && isset($configuration[$keyWithoutDot . '.'])) {
                $configuration[$keyWithoutDot] = $this->resolveTypoScriptConfiguration($value, $contentObjectRenderer);
            }
            unset($configuration[$keyWithoutDot . '.']);
        }
        return $configuration;
    }

}
