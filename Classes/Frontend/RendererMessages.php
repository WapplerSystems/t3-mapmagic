<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The visible texts of the renderer (MapMagicJs/src/render/messages.ts) in
 * the language of the request: site language in the frontend, user language
 * in the backend editor preview.
 */
final class RendererMessages
{
    private const KEYS = [
        'scrollHint', 'scrollHintMac', 'fullscreen', 'fullscreenExit', 'legend', 'filter', 'filterAll', 'filterNone',
        'elevationLength', 'elevationAscent', 'elevationDescent', 'elevationAxis', 'layerError',
    ];

    /**
     * @return array<string, string>
     */
    public static function for(ServerRequestInterface $request): array
    {
        $messages = [];
        foreach (self::KEYS as $key) {
            $label = LocalizationUtility::translate('frontend.' . $key, 'MapMagic', null, null, $request);
            // Missing translations fall back to the renderer's built-in text.
            if ($label !== null && $label !== '') {
                $messages[$key] = $label;
            }
        }
        return $messages;
    }
}
