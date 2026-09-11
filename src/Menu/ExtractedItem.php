<?php

declare(strict_types=1);

namespace App\Menu;

/**
 * Un producto tal y como lo ha leído el modelo, antes de guardarlo.
 */
final class ExtractedItem
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $category = null,
        public readonly ?string $description = null,
        public readonly array $options = [],
        public readonly ?float $price = null,
        public readonly ?string $priceText = null,
        public readonly ?string $sourceRef = null,
        public readonly bool $needsReview = false,
    ) {
    }
}
