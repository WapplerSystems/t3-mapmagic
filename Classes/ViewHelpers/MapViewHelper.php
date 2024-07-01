<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\MapMagic\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Custom form ViewHelper that renders the map
 *
 * Scope: frontend
 */
final class MapViewHelper extends AbstractTagBasedViewHelper
{

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('object', 'mixed', 'Object to use for the form. Use in conjunction with the "property" attribute on the sub tags');
        $this->registerArgument('section', 'string', 'The anchor to be added to the action URI (only active if $actionUri is not set)', false, '');
        $this->registerArgument('additionalParams', 'array', 'additional action URI query parameters that won\'t be prefixed like $arguments (overrule $arguments) (only active if $actionUri is not set)', false, []);
        $this->registerArgument('objectName', 'string', 'name of the object that is bound to this form. If this argument is not specified, the name attribute of this form is used to determine the MapObjectName');
        $this->registerUniversalTagAttributes();
    }


    protected function getMapRuntime(): MapRuntime
    {
        return $this->arguments['object'];
    }


    public function render()
    {
        $this->tag->setContent((string)$this->renderChildren());

        return $this->tag->render();
    }

}
