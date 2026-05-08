<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\B2bInclusion;

use Adminos\Modules\Feedmanager\Models\ShoptetCategory;

/**
 * Imutabilní výsledek vyhodnocení B2B inclusion pro jeden produkt. Nese flag
 * `included` plus enum reason a volitelný human-readable detail (např. název
 * vyřazené kategorie).
 *
 * @api
 */
final class B2bInclusionResult
{
    public function __construct(
        public readonly B2bInclusionReason $reason,
        public readonly ?ShoptetCategory $excludingCategory = null,
    ) {
    }

    public function isIncluded(): bool
    {
        return $this->reason->isIncluded();
    }

    public function isExcluded(): bool
    {
        return ! $this->isIncluded();
    }
}
