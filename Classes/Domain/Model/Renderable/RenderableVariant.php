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
use WapplerSystems\MapMagic\Domain\Exception\IdentifierNotValidException;

/**
 * Scope: frontend
 * **This class is NOT meant to be sub classed by developers.**
 * @internal
 */
class RenderableVariant implements RenderableVariantInterface
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var VariableRenderableInterface
     */
    protected $renderable;

    /**
     * @var string
     */
    protected $condition = '';

    /**
     * @var bool
     */
    protected $applied = false;

    /**
     * @throws IdentifierNotValidException
     */
    public function __construct(
        string $identifier,
        array $options,
        VariableRenderableInterface $renderable
    ) {
        if ($identifier === '') {
            throw new IdentifierNotValidException('The given variant identifier was empty.', 1519998923);
        }
        $this->identifier = $identifier;
        $this->renderable = $renderable;

        if (isset($options['condition']) && is_string($options['condition'])) {
            $this->condition = $options['condition'];
        }

        unset($options['condition'], $options['identifier'], $options['variants']);

        $this->options = $options;
    }

    /**
     * Apply the specified variant to this form element
     * regardless of their conditions
     */
    public function apply(): void
    {
        $this->renderable->setOptions($this->options, true);
        $this->applied = true;
    }

    public function conditionMatches(Resolver $conditionResolver): bool
    {
        if (empty($this->condition)) {
            return false;
        }

        return (bool)$conditionResolver->evaluate($this->condition, ['renderable' => $this->renderable]);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function isApplied(): bool
    {
        return $this->applied;
    }
}
