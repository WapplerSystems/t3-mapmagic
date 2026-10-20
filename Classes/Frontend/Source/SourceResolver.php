<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Frontend\Source;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class SourceResolver
{
    /**
     * @param iterable<SourceProviderInterface> $providers sorted by tag priority, highest first
     */
    public function __construct(
        #[AutowireIterator(SourceProviderInterface::TAG)]
        private iterable $providers,
    ) {}

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function resolve(array $source, ServerRequestInterface $request): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider->resolve($source, $request);
            }
        }
        throw new \InvalidArgumentException(sprintf('No provider for source type "%s"', (string)($source['type'] ?? '')), 1792460005);
    }
}
