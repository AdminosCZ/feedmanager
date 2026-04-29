<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

/**
 * One Shoptet category row, as understood by the importer. Built either from
 * the CSV export or the XML feed.
 *
 * @api
 */
final class ParsedCategory
{
    public function __construct(
        public readonly int $shoptet_id,
        public readonly ?int $parent_shoptet_id,
        public readonly string $title,
        public readonly ?string $guid = null,
        public readonly ?string $link_text = null,
        public readonly int $priority = 0,
        public readonly bool $visible = true,
    ) {
    }
}
