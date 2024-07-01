<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\MapMagic\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RenderableInterface;
use WapplerSystems\MapMagic\Domain\Model\Renderable\RootRenderableInterface;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Render a renderable.
 *
 * Set the renderable into the \WapplerSystems\MapMagic\Mvc\View\MapView
 * and return the rendered content.
 *
 * Scope: frontend
 */
final class RenderRenderableViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('renderable', RootRenderableInterface::class, 'A RenderableInterface instance', true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        /** @var MapRuntime $formRuntime */
        $formRuntime = $renderingContext
            ->getViewHelperVariableContainer()
            ->get(self::class, 'formRuntime');

        $renderable = $arguments['renderable'];

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/mapmagic']['beforeRendering'] ?? [] as $className) {
            $hookObj = GeneralUtility::makeInstance($className);
            if (method_exists($hookObj, 'beforeRendering')) {
                $hookObj->beforeRendering(
                    $formRuntime,
                    $renderable
                );
            }
        }

        $content = '';

        if ($renderable instanceof MapRuntime || $renderable instanceof RenderableInterface && $renderable->isEnabled()) {
            $content = $renderChildrenClosure();
        }

        // Wrap every renderable with a span with an identifier path data attribute if previewMode is active
        if (!empty($content)) {
            $renderingOptions = $formRuntime->getRenderingOptions();
            if (isset($renderingOptions['previewMode']) && $renderingOptions['previewMode'] === true) {
                $path = $renderable->getIdentifier();
                if ($renderable instanceof RenderableInterface) {
                    while ($renderable = $renderable->getParentRenderable()) {
                        $path = $renderable->getIdentifier() . '/' . $path;
                    }
                }
                $content = '<span data-element-identifier-path="' . $path . '">' . $content . '</span>';
            }
        }
        return $content;
    }
}
