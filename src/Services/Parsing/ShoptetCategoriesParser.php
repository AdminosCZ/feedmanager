<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\Parsing;

use Generator;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parses the Shoptet category-tree export. Shoptet ships two flavours from
 * the same admin endpoint depending on URL params:
 *
 *  - CSV (the partner-feed pattern):
 *      `https://{eshop}.cz/export/categories.csv?partnerId={id}&hash={hash}`
 *      Semicolon-separated, Windows-1250, columns:
 *      id;parentId;parentUrl;expandInMenu;visible;priority;access;title;linkText;url
 *
 *  - XML (the system feed):
 *      `<SHOP><CATEGORIES><CATEGORY>...</CATEGORY></CATEGORIES></SHOP>`
 *      with elements ID / PARENT_ID / GUID / TITLE / LINK_TEXT / PRIORITY /
 *      VISIBLE.
 *
 * Auto-detection peeks at the first non-whitespace byte: `<` → XML, anything
 * else → CSV. The parser yields {@see ParsedCategory} regardless of source so
 * downstream code is format-agnostic.
 *
 * @api
 */
final class ShoptetCategoriesParser
{
    private const CSV_DELIMITER = ';';

    private const CSV_ENCLOSURE = '"';

    /**
     * @return Generator<int, ParsedCategory>
     */
    public function parse(string $payload): Generator
    {
        if (trim($payload) === '') {
            throw new RuntimeException('Empty Shoptet categories payload.');
        }

        if ($this->looksLikeXml($payload)) {
            yield from $this->parseXml($payload);

            return;
        }

        yield from $this->parseCsv($payload);
    }

    private function looksLikeXml(string $payload): bool
    {
        return str_starts_with(ltrim($payload), '<');
    }

    /**
     * @return Generator<int, ParsedCategory>
     */
    private function parseCsv(string $payload): Generator
    {
        $utf8 = $this->toUtf8($payload);

        $lines = $this->splitLines($utf8);
        if ($lines === []) {
            throw new RuntimeException('Shoptet categories CSV has no rows.');
        }

        $header = str_getcsv(array_shift($lines), self::CSV_DELIMITER, self::CSV_ENCLOSURE, '\\');

        $columns = [];
        foreach ($header as $index => $name) {
            $columns[trim((string) $name)] = $index;
        }

        $idIdx = $columns['id'] ?? null;
        $parentIdx = $columns['parentId'] ?? null;
        $titleIdx = $columns['title'] ?? null;

        if ($idIdx === null || $titleIdx === null) {
            throw new RuntimeException(
                'Shoptet categories CSV is missing required columns "id" or "title". '
                .'Got: '.implode(', ', array_keys($columns))
            );
        }

        $linkTextIdx = $columns['linkText'] ?? null;
        $priorityIdx = $columns['priority'] ?? null;
        $visibleIdx = $columns['visible'] ?? null;

        foreach ($lines as $rawRow) {
            if (trim($rawRow) === '') {
                continue;
            }

            $row = str_getcsv($rawRow, self::CSV_DELIMITER, self::CSV_ENCLOSURE, '\\');

            $id = $this->intOrNull($row[$idIdx] ?? null);
            $title = trim((string) ($row[$titleIdx] ?? ''));

            if ($id === null || $id <= 0 || $title === '') {
                continue;
            }

            yield new ParsedCategory(
                shoptet_id: $id,
                parent_shoptet_id: $this->intOrNull($row[$parentIdx] ?? null),
                title: $title,
                link_text: $linkTextIdx !== null ? $this->stringOrNull($row[$linkTextIdx] ?? null) : null,
                priority: $priorityIdx !== null ? ($this->intOrNull($row[$priorityIdx] ?? null) ?? 0) : 0,
                visible: $visibleIdx !== null ? $this->parseBool((string) ($row[$visibleIdx] ?? '1')) : true,
            );
        }
    }

    /**
     * @return Generator<int, ParsedCategory>
     */
    private function parseXml(string $payload): Generator
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($payload);

            if ($xml === false) {
                $errors = array_map(
                    static fn ($e): string => trim($e->message),
                    libxml_get_errors(),
                );
                libxml_clear_errors();
                throw new RuntimeException(
                    'Shoptet categories XML is malformed: '.($errors[0] ?? 'unknown error')
                );
            }
        } finally {
            libxml_use_internal_errors($previous);
        }

        // Categories live under <CATEGORIES><CATEGORY/></CATEGORIES> at root
        // (system feed) or — defensively — directly at root for variants. Use
        // XPath to avoid depending on the root element name.
        $nodes = $xml->xpath('//CATEGORY');
        if ($nodes === false || $nodes === []) {
            return;
        }

        foreach ($nodes as $node) {
            $parsed = $this->parseXmlNode($node);
            if ($parsed !== null) {
                yield $parsed;
            }
        }
    }

    private function parseXmlNode(SimpleXMLElement $node): ?ParsedCategory
    {
        $id = $this->intOrNull((string) ($node->ID ?? ''));
        $title = trim((string) ($node->TITLE ?? ''));

        if ($id === null || $id <= 0 || $title === '') {
            return null;
        }

        return new ParsedCategory(
            shoptet_id: $id,
            parent_shoptet_id: $this->intOrNull((string) ($node->PARENT_ID ?? '')),
            title: $title,
            guid: $this->stringOrNull((string) ($node->GUID ?? '')),
            link_text: $this->stringOrNull((string) ($node->LINK_TEXT ?? '')),
            priority: $this->intOrNull((string) ($node->PRIORITY ?? '')) ?? 0,
            visible: $this->parseBool((string) ($node->VISIBLE ?? '1')),
        );
    }

    private function toUtf8(string $payload): string
    {
        if (mb_check_encoding($payload, 'UTF-8')) {
            return $payload;
        }

        $converted = @iconv('Windows-1250', 'UTF-8//IGNORE', $payload);

        return $converted !== false ? $converted : $payload;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $payload): array
    {
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

    private function stringOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseBool(string $value): bool
    {
        $normalised = strtolower(trim($value));

        return in_array($normalised, ['1', 'true', 'yes', 'ano'], true);
    }
}
