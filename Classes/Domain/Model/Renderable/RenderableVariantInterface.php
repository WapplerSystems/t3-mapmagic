<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Domain\Model\Renderable;

use TYPO3\CMS\Core\ExpressionLanguage\Resolver;

/**
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 * @internal
 */
interface RenderableVariantInterface
{
    public function getIdentifier(): string;

    /**
     * Apply the specified variant to this form element
     * regardless of their conditions
     */
    public function apply(): void;

    public function isApplied(): bool;

    public function conditionMatches(Resolver $conditionResolver): bool;
}
