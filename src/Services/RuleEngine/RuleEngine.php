<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services\RuleEngine;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\FeedRule;
use Adminos\Modules\Feedmanager\Services\Parsing\ParsedProduct;

/**
 * Applies a list of {@see FeedRule}s to a {@see ParsedProduct} during import.
 *
 * Rules are loaded from the DB scoped by feed_config_id (specific) plus
 * supplier_id (broader). They run in priority order (lower = earlier). Each
 * rule evaluates a condition against one ParsedProduct field; if it matches,
 * the action transforms that same field (or, in the case of `remove`,
 * vetoes the whole product).
 *
 * @api
 */
final class RuleEngine
{
    /**
     * Returns either the transformed ParsedProduct or null if a rule decided
     * to remove this product entirely.
     */
    public function apply(ParsedProduct $product, FeedConfig $config): ?ParsedProduct
    {
        $rules = $this->loadRules($config);

        if ($rules === []) {
            return $product;
        }

        $values = $this->extract($product);

        foreach ($rules as $rule) {
            if (! $this->conditionMatches($rule, $values)) {
                continue;
            }

            if ($rule->action === FeedRule::ACTION_REMOVE) {
                return null;
            }

            $values[$rule->field] = $this->applyAction($rule, $values[$rule->field] ?? null);
        }

        return $this->rebuild($product, $values);
    }

    /**
     * @return array<int, FeedRule>
     */
    private function loadRules(FeedConfig $config): array
    {
        return FeedRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($config): void {
                $q->where('feed_config_id', $config->id)
                    ->orWhere(function ($qq) use ($config): void {
                        $qq->whereNull('feed_config_id')
                            ->where('supplier_id', $config->supplier_id);
                    });
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function conditionMatches(FeedRule $rule, array $values): bool
    {
        $op = $rule->condition_op ?? FeedRule::COND_ALWAYS;

        if ($op === FeedRule::COND_ALWAYS) {
            return true;
        }

        $value = $values[$rule->field] ?? null;
        $needle = $rule->condition_value;

        return match ($op) {
            FeedRule::COND_EQ => (string) $value === (string) $needle,
            FeedRule::COND_NEQ => (string) $value !== (string) $needle,
            FeedRule::COND_CONTAINS => $value !== null && str_contains((string) $value, (string) $needle),
            FeedRule::COND_STARTS_WITH => $value !== null && str_starts_with((string) $value, (string) $needle),
            FeedRule::COND_ENDS_WITH => $value !== null && str_ends_with((string) $value, (string) $needle),
            FeedRule::COND_GT => $value !== null && is_numeric($value) && is_numeric($needle) && (float) $value > (float) $needle,
            FeedRule::COND_LT => $value !== null && is_numeric($value) && is_numeric($needle) && (float) $value < (float) $needle,
            FeedRule::COND_MATCHES => $value !== null && @preg_match((string) $needle, (string) $value) === 1,
            default => false,
        };
    }

    private function applyAction(FeedRule $rule, mixed $current): mixed
    {
        $value = $rule->action_value;

        return match ($rule->action) {
            FeedRule::ACTION_SET => $value,
            FeedRule::ACTION_ADD => $this->numeric($current) + $this->numeric($value),
            FeedRule::ACTION_SUBTRACT => $this->numeric($current) - $this->numeric($value),
            FeedRule::ACTION_MULTIPLY => $this->numeric($current) * $this->numeric($value),
            FeedRule::ACTION_DIVIDE => $this->numeric($value) === 0.0
                ? $current
                : $this->numeric($current) / $this->numeric($value),
            FeedRule::ACTION_REPLACE => $this->applyReplace((string) ($current ?? ''), (string) $value),
            FeedRule::ACTION_PREPEND => (string) $value . (string) ($current ?? ''),
            FeedRule::ACTION_APPEND => (string) ($current ?? '') . (string) $value,
            FeedRule::ACTION_ROUND => $current !== null && is_numeric($current)
                ? round((float) $current, (int) ($value ?? 0))
                : $current,
            default => $current,
        };
    }

    /**
     * `action_value` for replace is "needle|replacement" (pipe-separated).
     * If only one segment, it's treated as needle with empty replacement.
     */
    private function applyReplace(string $current, string $value): string
    {
        $parts = explode('|', $value, 2);
        $needle = $parts[0];
        $replacement = $parts[1] ?? '';

        if ($needle === '') {
            return $current;
        }

        // Allow regex replacement when the needle starts and ends with `/`.
        if (strlen($needle) > 1 && $needle[0] === '/' && substr($needle, -1) === '/') {
            $result = @preg_replace($needle, $replacement, $current);
            return $result ?? $current;
        }

        return str_replace($needle, $replacement, $current);
    }

    private function numeric(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($s = str_replace(',', '.', $value))) {
            return (float) $s;
        }
        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(ParsedProduct $product): array
    {
        return [
            'code' => $product->code,
            'name' => $product->name,
            'ean' => $product->ean,
            'product_number' => $product->product_number,
            'description' => $product->description,
            'manufacturer' => $product->manufacturer,
            'price' => $product->price,
            'price_vat' => $product->price_vat,
            'old_price_vat' => $product->old_price_vat,
            'currency' => $product->currency,
            'stock_quantity' => $product->stock_quantity,
            'availability' => $product->availability,
            'image_url' => $product->image_url,
            'category_text' => $product->category_text,
            'complete_path' => $product->complete_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function rebuild(ParsedProduct $original, array $values): ParsedProduct
    {
        return new ParsedProduct(
            code: (string) ($values['code'] ?? $original->code),
            name: (string) ($values['name'] ?? $original->name),
            ean: $this->stringOrNull($values['ean'] ?? null),
            product_number: $this->stringOrNull($values['product_number'] ?? null),
            description: $this->stringOrNull($values['description'] ?? null),
            manufacturer: $this->stringOrNull($values['manufacturer'] ?? null),
            price: $this->floatOrNull($values['price'] ?? null),
            price_vat: $this->floatOrNull($values['price_vat'] ?? null),
            old_price_vat: $this->floatOrNull($values['old_price_vat'] ?? null),
            currency: (string) ($values['currency'] ?? $original->currency),
            stock_quantity: $this->intOrNull($values['stock_quantity'] ?? null),
            availability: $this->stringOrNull($values['availability'] ?? null),
            image_url: $this->stringOrNull($values['image_url'] ?? null),
            category_text: $this->stringOrNull($values['category_text'] ?? null),
            complete_path: $this->stringOrNull($values['complete_path'] ?? null),
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = (string) $value;
        return $s === '' ? null : $s;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($s = str_replace(',', '.', $value))) {
            return (float) $s;
        }
        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }
}
