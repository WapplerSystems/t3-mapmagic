<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Runtime\MapRuntime\Lifecycle;

use WapplerSystems\MapMagic\Domain\Runtime\MapRuntime;

/**
 * Event is triggered with current form state and form session, which is
 * not the case with e.g. `afterBuildingFinished`. Can be used to further
 * enrich components with runtime state.
 * @internal
 */
interface AfterMapStateInitializedInterface
{
    /**
     * @param MapRuntime $formRuntime holding current form state and static form definition
     */
    public function afterMapStateInitialized(MapRuntime $formRuntime): void;
}
