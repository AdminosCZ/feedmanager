<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\B2bInclusion;

/**
 * Důvody, proč produkt JE nebo NENÍ v partnerském B2B feedu. Slouží admin UI
 * (ProductInfolist „B2B status" karta) a logování / audit trail. Pořadí
 * konstant odpovídá vyhodnocovacím prioritám v {@see B2bInclusionResolver}.
 *
 * @api
 */
enum B2bInclusionReason: string
{
    case INCLUDED_DEFAULT = 'included_default';

    case INCLUDED_FORCE_ALLOWED = 'included_force_allowed';

    case EXCLUDED_GLOBALLY = 'excluded_globally';

    case EXCLUDED_FORCE_EXCLUDED = 'excluded_force_excluded';

    case EXCLUDED_MASTER_OFF = 'excluded_master_off';

    case EXCLUDED_PAUSED = 'excluded_paused';

    case EXCLUDED_CATEGORY = 'excluded_category';

    public function isIncluded(): bool
    {
        return match ($this) {
            self::INCLUDED_DEFAULT, self::INCLUDED_FORCE_ALLOWED => true,
            default => false,
        };
    }
}
