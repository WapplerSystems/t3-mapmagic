<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Configuration;

use TYPO3\CMS\Core\TypoScript\TypoScriptService as CoreTypoScriptService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
    public function resolvePossibleTypoScriptConfiguration(array $configuration = []): array
    {
        $configuration = $this->coreTypoScriptService->convertPlainArrayToTypoScriptArray($configuration);
        $configuration = $this->resolveTypoScriptConfiguration($configuration);
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
     * @param array $configuration
     */
    protected function resolveTypoScriptConfiguration(array $configuration = []): array
    {
        foreach ($configuration as $key => $value) {
            $keyWithoutDot = rtrim((string)$key, '.');
            if (isset($configuration[$keyWithoutDot]) && isset($configuration[$keyWithoutDot . '.'])) {
                $value = $this->getTypoScriptFrontendController()->cObj->cObjGetSingle(
                    $configuration[$keyWithoutDot],
                    $configuration[$keyWithoutDot . '.'],
                    $keyWithoutDot
                );
                $configuration[$keyWithoutDot] = $value;
            } elseif (!isset($configuration[$keyWithoutDot]) && isset($configuration[$keyWithoutDot . '.'])) {
                $configuration[$keyWithoutDot] = $this->resolveTypoScriptConfiguration($value);
            }
            unset($configuration[$keyWithoutDot . '.']);
        }
        return $configuration;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}
