<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Services;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

/**
 * Inspects a feed payload **without writing to the catalog**. Useful before
 * wiring up a real FeedConfig: paste a URL, optional basic auth, get back
 * stats + sample products + detected issues.
 *
 * Consumes the same FeedParserFactory the importer uses, so what you see in
 * the explorer matches what would actually land during import.
 *
 * @api
 */
final class FeedExplorerService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly FeedParserFactory $parsers,
        private readonly int $sampleSize = 5,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    /**
     * @return array{
     *     source: string,
     *     format: string,
     *     payload_bytes: int,
     *     download_ms: int|null,
     *     total_products: int,
     *     fields: array<int, array{name: string, filled: int, percent: float}>,
     *     issues: array<int, array{key: string, severity: string, count: int}>,
     *     samples: array<int, array<string, mixed>>,
     *     category_counts: array<int, array{path: string, count: int}>,
     * }
     */
    public function analyzeUrl(string $url, string $format, ?string $username = null, ?string $password = null): array
    {
        $started = microtime(true);

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->withHeaders(['User-Agent' => 'ADMINOS Feedmanager Explorer/0.1'])
                ->when(
                    $username !== null && $username !== '',
                    fn ($request) => $request->withBasicAuth($username, (string) $password),
                )
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException('Connection failed: ' . $e->getMessage(), 0, $e);
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Source returned HTTP %d for %s.',
                $response->status(),
                $url,
            ));
        }

        return $this->analyzePayload((string) $response->body(), $format, $url, $elapsedMs);
    }

    /**
     * @return array{
     *     source: string,
     *     format: string,
     *     payload_bytes: int,
     *     download_ms: int|null,
     *     total_products: int,
     *     fields: array<int, array{name: string, filled: int, percent: float}>,
     *     issues: array<int, array{key: string, severity: string, count: int}>,
     *     samples: array<int, array<string, mixed>>,
     *     category_counts: array<int, array{path: string, count: int}>,
     * }
     */
    public function analyzePayload(string $payload, string $format, string $sourceLabel = 'inline', ?int $downloadMs = null): array
    {
        if (! in_array($format, FeedConfig::FORMATS, true)) {
            throw new RuntimeException(sprintf(
                'Unknown feed format "%s". Must be one of: %s.',
                $format,
                implode(', ', FeedConfig::FORMATS),
            ));
        }

        $parser = $this->parsers->for($format);

        $totalProducts = 0;
        $fieldFilled = $this->fieldKeysToZero();
        $issues = [
            'missing_name' => 0,
            'missing_code' => 0,
            'missing_price' => 0,
            'missing_image' => 0,
            'missing_category' => 0,
            'missing_ean' => 0,
            'duplicate_codes' => 0,
        ];
        $seenCodes = [];
        $samples = [];
        $categoryCounts = [];

        try {
            foreach ($parser->parse($payload) as $product) {
                ++$totalProducts;

                if ($product->name === '') {
                    ++$issues['missing_name'];
                } else {
                    ++$fieldFilled['name'];
                }

                if ($product->code === '') {
                    ++$issues['missing_code'];
                } else {
                    ++$fieldFilled['code'];
                    if (isset($seenCodes[$product->code])) {
                        ++$issues['duplicate_codes'];
                    } else {
                        $seenCodes[$product->code] = true;
                    }
                }

                if ($product->price_vat === null && $product->price === null) {
                    ++$issues['missing_price'];
                }
                if ($product->price !== null) {
                    ++$fieldFilled['price'];
                }
                if ($product->price_vat !== null) {
                    ++$fieldFilled['price_vat'];
                }

                if ($product->image_url === null || $product->image_url === '') {
                    ++$issues['missing_image'];
                } else {
                    ++$fieldFilled['image_url'];
                }

                $categoryPath = $product->complete_path ?? $product->category_text;
                if ($categoryPath === null || $categoryPath === '') {
                    ++$issues['missing_category'];
                } else {
                    ++$fieldFilled['category'];
                    $categoryCounts[$categoryPath] = ($categoryCounts[$categoryPath] ?? 0) + 1;
                }

                if ($product->ean === null || $product->ean === '') {
                    ++$issues['missing_ean'];
                } else {
                    ++$fieldFilled['ean'];
                }

                if ($product->description !== null && $product->description !== '') {
                    ++$fieldFilled['description'];
                }
                if ($product->stock_quantity !== null) {
                    ++$fieldFilled['stock_quantity'];
                }
                if ($product->availability !== null && $product->availability !== '') {
                    ++$fieldFilled['availability'];
                }
                if ($product->manufacturer !== null && $product->manufacturer !== '') {
                    ++$fieldFilled['manufacturer'];
                }

                if (count($samples) < $this->sampleSize) {
                    $samples[] = [
                        'code' => $product->code,
                        'name' => $product->name,
                        'price_vat' => $product->price_vat,
                        'currency' => $product->currency,
                        'stock_quantity' => $product->stock_quantity,
                        'availability' => $product->availability,
                        'category' => $categoryPath,
                        'ean' => $product->ean,
                    ];
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Parsing failed: ' . $e->getMessage(), 0, $e);
        }

        $fields = [];
        foreach ($fieldFilled as $name => $count) {
            $fields[] = [
                'name' => $name,
                'filled' => $count,
                'percent' => $totalProducts > 0 ? round($count / $totalProducts * 100, 1) : 0.0,
            ];
        }
        usort($fields, fn (array $a, array $b): int => $b['filled'] <=> $a['filled']);

        $issuesOut = [];
        foreach ($issues as $key => $count) {
            if ($count === 0) {
                continue;
            }
            $issuesOut[] = [
                'key' => $key,
                'severity' => in_array($key, ['missing_name', 'missing_code', 'missing_price'], true)
                    ? 'error'
                    : 'warning',
                'count' => $count,
            ];
        }

        arsort($categoryCounts);
        $topCategories = [];
        $i = 0;
        foreach ($categoryCounts as $path => $count) {
            if ($i++ >= 20) {
                break;
            }
            $topCategories[] = ['path' => $path, 'count' => $count];
        }

        return [
            'source' => $sourceLabel,
            'format' => $format,
            'payload_bytes' => strlen($payload),
            'download_ms' => $downloadMs,
            'total_products' => $totalProducts,
            'fields' => $fields,
            'issues' => $issuesOut,
            'samples' => $samples,
            'category_counts' => $topCategories,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function fieldKeysToZero(): array
    {
        return [
            'code' => 0,
            'name' => 0,
            'description' => 0,
            'price' => 0,
            'price_vat' => 0,
            'ean' => 0,
            'manufacturer' => 0,
            'stock_quantity' => 0,
            'availability' => 0,
            'image_url' => 0,
            'category' => 0,
        ];
    }
}
