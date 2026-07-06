<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Resources\ProductResource;

class ProductMatcher
{
    private ProductResource $products;

    public function __construct(ProductResource $products)
    {
        $this->products = $products;
    }

    /**
     * Find best matching product by voice query
     *
     * @param  string  $query  Voice input (e.g., "Pepsi", "Pepsi large", "Pepsi 500ml")
     * @param  array|null  $queryAlternatives  AI-suggested alternative spellings
     */
    public function findBestMatch(string $query, ?array $queryAlternatives = null): ProductMatchResult
    {
        // Step 1: Search Daftra products API (with AI-suggested alternatives)
        $allProducts = $this->searchWithAlternatives($query, $queryAlternatives ?? []);

        if (empty($allProducts)) {
            return ProductMatchResult::notFound($query);
        }

        // Step 2: Score each product by relevance
        $scored = array_map(function ($product) use ($query) {
            return [
                'product' => $product,
                'score' => $this->calculateRelevanceScore($product, $query),
            ];
        }, $allProducts);

        // Step 3: Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $topMatch = $scored[0];
        $secondMatch = $scored[1] ?? null;

        // Step 4: Determine confidence level
        if ($topMatch['score'] >= 85) {
            return ProductMatchResult::highConfidence(
                product: $topMatch['product'],
                score: $topMatch['score'],
            );
        }

        if ($secondMatch && ($topMatch['score'] - $secondMatch['score']) < 15) {
            // Ambiguous - top 2 are very close
            $alternatives = array_slice($scored, 0, 3);

            return ProductMatchResult::ambiguous(
                topMatch: $topMatch['product'],
                alternatives: array_column($alternatives, 'product'),
                scores: array_column($alternatives, 'score'),
            );
        }

        return ProductMatchResult::mediumConfidence(
            product: $topMatch['product'],
            score: $topMatch['score'],
        );
    }

    /**
     * Search products by original query then try AI-suggested alternatives
     */
    private function searchWithAlternatives(string $query, array $alternatives): array
    {
        $response = $this->products->list([
            'keywords' => $query,
            'per_page' => 20,
        ]);

        $products = $response->data ?? [];

        if (count($products) < 3) {
            foreach ($alternatives as $alt) {
                if (strtolower($alt) === strtolower($query)) {
                    continue;
                }

                $altResponse = $this->products->list(['keywords' => $alt, 'per_page' => 20]);

                if (! empty($altResponse->data)) {
                    foreach ($altResponse->data as $candidate) {
                        $products[] = $candidate;
                    }
                }
            }

            // Deduplicate by product ID
            $seen = [];
            $products = array_values(array_filter($products, function ($p) use (&$seen) {
                $id = $p['Product']['id'] ?? $p['id'] ?? spl_object_id($p);

                if (isset($seen[$id])) {
                    return false;
                }

                $seen[$id] = true;

                return true;
            }));
        }

        return $products;
    }

    /**
     * Calculate relevance score (0-100)
     */
    private function calculateRelevanceScore($product, string $query): float
    {
        $name = strtolower($product['name'] ?? '');
        $query = strtolower($query);

        $score = 0;

        // Exact match = 100
        if ($name === $query) {
            return 100;
        }

        // Starts with query = 90
        if (str_starts_with($name, $query)) {
            $score += 90;
        }
        // Contains query = 70
        elseif (str_contains($name, $query)) {
            $score += 70;
        }
        // Fuzzy match (Levenshtein distance)
        else {
            $distance = levenshtein($name, $query);
            $maxLen = max(strlen($name), strlen($query));
            $similarity = (1 - ($distance / $maxLen)) * 60;
            $score += $similarity;
        }

        // Boost: Product is active/available
        if (($product['is_active'] ?? true)) {
            $score += 5;
        }

        // Boost: Product has stock
        if (($product['stock_quantity'] ?? 0) > 0) {
            $score += 5;
        }

        // Boost: Popular product (has recent sales)
        if (($product['total_sold'] ?? 0) > 10) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Get product by exact ID (for corrections)
     */
    public function getById(int $productId): ?array
    {
        $response = $this->products->get($productId);

        return $response->data ?? null;
    }
}

class ProductMatchResult
{
    public function __construct(
        public readonly string $status, // 'high_confidence', 'medium_confidence', 'ambiguous', 'not_found'
        public readonly ?array $product = null,
        public readonly float $score = 0,
        public readonly array $alternatives = [],
        public readonly ?string $query = null,
    ) {}

    public static function highConfidence(array $product, float $score): self
    {
        return new self('high_confidence', $product, $score);
    }

    public static function mediumConfidence(array $product, float $score): self
    {
        return new self('medium_confidence', $product, $score);
    }

    public static function ambiguous(array $topMatch, array $alternatives, array $scores): self
    {
        return new self(
            status: 'ambiguous',
            product: $topMatch,
            score: $scores[0] ?? 0,
            alternatives: $alternatives,
        );
    }

    public static function notFound(string $query): self
    {
        return new self('not_found', query: $query);
    }

    public function isHighConfidence(): bool
    {
        return $this->status === 'high_confidence';
    }

    public function isAmbiguous(): bool
    {
        return $this->status === 'ambiguous';
    }

    public function needsClarification(): bool
    {
        return $this->status === 'ambiguous' || $this->status === 'not_found';
    }

    public function clarificationQuestion(): string
    {
        return match ($this->status) {
            'ambiguous' => $this->buildAmbiguityQuestion(),
            'not_found' => "I couldn't find a product matching '{$this->query}'. Can you describe it differently?",
            default => '',
        };
    }

    private function buildAmbiguityQuestion(): string
    {
        $options = array_slice($this->alternatives, 0, 3);
        $names = array_map(fn ($p) => $p['Product']['name'], $options);

        if (count($names) === 2) {
            return "Did you mean {$names[0]} or {$names[1]}?";
        }

        $last = array_pop($names);

        return 'Did you mean '.implode(', ', $names).", or {$last}?";
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'product' => $this->product,
            'score' => $this->score,
            'alternatives' => $this->alternatives,
            'needs_clarification' => $this->needsClarification(),
            'clarification_question' => $this->needsClarification() ? $this->clarificationQuestion() : null,
        ];
    }
}
