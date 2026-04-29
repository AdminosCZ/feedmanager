<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;

/**
 * Parses Shoptet stock statistics CSV — the only Shoptet export that includes
 * per-product stock count.
 *
 * Default URL pattern (B2B-partner-specific token):
 *   https://{eshop}.cz/export/stockStatistics.csv?partnerId={id}&hash={hash}
 *
 * Format:
 *  - Semicolon-separated values
 *  - Double-quoted strings, doubled quotes for embedded `"` (RFC 4180-ish)
 *  - Encoding: Windows-1250 (cp1250 / Latin-2)
 *  - Header: itemCode;itemName;stock;minStockSupply;stockStatus;daysSinceLastSale;totalPurchasePrice;currency
 *
 * Code normalization (important):
 *   Stock CSV preserves the raw product code (e.g. `121/XS`, `121/S -`).
 *   Shoptet's seznam/heureka XML feeds sanitize codes (`/` → `_`, ` ` → `_`).
 *   To make the CSV match products imported via XML, this parser applies
 *   the same Shoptet-XML-style sanitization at parse time so downstream
 *   lookups can use exact match.
 *
 * This parser is meant to be used with `FeedConfig.update_only_mode = true`
 * so importer skips products that don't exist in the catalogue (CSV is a
 * stock supplement, not a primary catalogue source).
 *
 * @api
 */
final class ShoptetStockCsvParser implements FeedParser
{
    private const DELIMITER = ';';

    private const ENCLOSURE = '"';

    public function parse(string $payload): Generator
    {
        if (trim($payload) === '') {
            throw new RuntimeException('Empty Shoptet stock CSV payload.');
        }

        $utf8 = $this->toUtf8($payload);

        $lines = $this->splitLines($utf8);
        if ($lines === []) {
            throw new RuntimeException('Shoptet stock CSV has no rows.');
        }

        $header = str_getcsv(array_shift($lines), self::DELIMITER, self::ENCLOSURE, '\\');

        // Map column names to indexes. Shoptet may add columns; we only need
        // the ones we recognise.
        $columns = [];
        foreach ($header as $index => $name) {
            $columns[trim((string) $name)] = $index;
        }

        $codeIdx = $columns['itemCode'] ?? null;
        $nameIdx = $columns['itemName'] ?? null;
        $stockIdx = $columns['stock'] ?? null;

        if ($codeIdx === null || $stockIdx === null) {
            throw new RuntimeException(
                'Shoptet stock CSV is missing required columns "itemCode" or "stock". '
                . 'Got: ' . implode(', ', array_keys($columns))
            );
        }

        foreach ($lines as $rawRow) {
            if (trim($rawRow) === '') {
                continue;
            }

            $row = str_getcsv($rawRow, self::DELIMITER, self::ENCLOSURE, '\\');

            $code = $this->normalizeCode((string) ($row[$codeIdx] ?? ''));
            if ($code === '') {
                continue;
            }

            $name = trim((string) ($row[$nameIdx] ?? ''));
            // ParsedProduct requires a name. Use code as fallback so existing
            // products without name in CSV still flow through to the importer.
            if ($name === '') {
                $name = $code;
            }

            $stock = $this->intOrNull($row[$stockIdx] ?? null);

            yield new ParsedProduct(
                code: $code,
                name: $name,
                stock_quantity: $stock,
            );
        }
    }

    /**
     * Apply the same code sanitization Shoptet's seznam/heureka XML feeds use,
     * so products imported from CSV match products imported from XML.
     */
    public function normalizeCode(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        // Shoptet seznam/heureka feeds replace `/` and spaces with `_`; dashes
        // and alphanumerics are preserved.
        return str_replace(['/', ' '], '_', $trimmed);
    }

    private function toUtf8(string $payload): string
    {
        // Fast path — already valid UTF-8 (e.g. custom export). mbstring's
        // strict check rejects sequences that aren't valid UTF-8.
        if (mb_check_encoding($payload, 'UTF-8')) {
            return $payload;
        }

        // Czech Shoptet stock CSV always ships as Windows-1250 (cp1250).
        // mbstring doesn't list cp1250, so iconv handles the conversion.
        $converted = @iconv('Windows-1250', 'UTF-8//IGNORE', $payload);

        return $converted !== false ? $converted : $payload;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $payload): array
    {
        // Normalise line endings (Shoptet may emit CRLF) and split.
        $normalised = str_replace(["\r\n", "\r"], "\n", $payload);
        $lines = explode("\n", $normalised);

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    private function intOrNull(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }
}
