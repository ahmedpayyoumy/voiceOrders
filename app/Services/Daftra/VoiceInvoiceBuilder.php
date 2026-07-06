<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Resources\ClientResource;
use App\Services\Daftra\Resources\ProductResource;
use Illuminate\Support\Facades\Http;

/**
 * Handles voice invoice creation with intelligent product matching
 */
class VoiceInvoiceBuilder
{
    private ProductMatcher $productMatcher;

    private ClientResource $clients;

    private ProductResource $products;

    public function __construct(DaftraClient $client)
    {
        $this->products = new ProductResource($client);
        $this->productMatcher = new ProductMatcher($this->products);
        $this->clients = new ClientResource($client);
    }

    /**
     * Parse voice transcript and prepare invoice with smart product matching
     *
     * @param  string  $transcript  Voice input
     * @param  int|null  $selectedCustomerId  Pre-selected customer ID from clarification
     * @param  int|null  $selectedProductId  Pre-selected product ID from clarification
     * @param  string|null  $selectedProductQuery  Original product query text from clarification
     */
    public function prepareInvoice(
        string $transcript,
        ?int $selectedCustomerId = null,
        ?int $selectedProductId = null,
        ?string $selectedProductQuery = null,
    ): InvoicePreparationResult {
        // Step 1: Extract entities using AI
        $parsed = $this->parseTranscript($transcript);

        if (! $parsed['success']) {
            return InvoicePreparationResult::failed($parsed['error']);
        }

        // Step 2: Resolve customer (skip if already selected via clarification)
        if ($selectedCustomerId) {
            $response = $this->clients->get($selectedCustomerId);
            $customerResult = CustomerResolutionResult::found(
                $response->data ?? []
            );
        } else {
            $customerResult = $this->resolveCustomer($parsed['customer_name'] ?? null);
        }

        if ($customerResult->needsClarification()) {
            return InvoicePreparationResult::needsCustomerClarification(
                $customerResult->clarificationQuestion(),
                $customerResult->alternatives ?? []
            );
        }

        // Step 3: Match products intelligently
        $lineItems = [];
        $ambiguousProducts = [];

        foreach ($parsed['items'] as $item) {
            $productName = $item['product_name'];

            // Skip product matcher for pre-selected product from clarification
            if ($selectedProductId && $selectedProductQuery && strcasecmp($productName, $selectedProductQuery) === 0) {
                $product = $this->productMatcher->getById($selectedProductId);
                if (! $product) {
                    return InvoicePreparationResult::failed(
                        "Selected product (ID: {$selectedProductId}) not found."
                    );
                }

                $lineItems[] = [
                    'product_id' => $product['Product']['id'] ?? $product['id'],
                    'product_name' => $product['Product']['name'] ?? $product['name'],
                    'quantity' => $item['quantity'],
                    'price' => $product['Product']['unit_price'] ?? $product['unit_price'] ?? 0,
                    'total' => ($product['Product']['unit_price'] ?? $product['unit_price'] ?? 0) * $item['quantity'],
                ];

                continue;
            }

            $matchResult = $this->productMatcher->findBestMatch($productName);

            if ($matchResult->isAmbiguous()) {
                $ambiguousProducts[] = [
                    'query' => $productName,
                    'quantity' => $item['quantity'],
                    'alternatives' => $matchResult->alternatives,
                    'question' => $matchResult->clarificationQuestion(),
                ];

                continue;
            }

            if ($matchResult->status === 'not_found') {
                return InvoicePreparationResult::failed(
                    "Product '{$productName}' not found in your catalog. Please check the name."
                );
            }

            $lineItems[] = [
                'product_id' => $matchResult->product['Product']['id'] ?? $matchResult->product['id'],
                'product_name' => $matchResult->product['Product']['name'] ?? $matchResult->product['name'],
                'quantity' => $item['quantity'],
                'price' => $matchResult->product['Product']['unit_price'] ?? $matchResult->product['unit_price'] ?? 0,
                'total' => ($matchResult->product['Product']['unit_price'] ?? $matchResult->product['unit_price'] ?? 0) * $item['quantity'],
                'confidence' => $matchResult->score,
            ];
        }

        // Step 4: Handle ambiguous products
        if (! empty($ambiguousProducts)) {
            return InvoicePreparationResult::needsProductClarification(
                $ambiguousProducts,
                $lineItems, // Include already-matched items
                $customerResult->customer ?? null,
            );
        }

        // Step 5: Build invoice preview
        $total = array_sum(array_column($lineItems, 'total'));

        return InvoicePreparationResult::ready(
            customer: $customerResult->customer,
            lineItems: $lineItems,
            total: $total,
            originalTranscript: $transcript
        );
    }

    /**
     * Parse transcript using AI to extract structured data
     */
    private function parseTranscript(string $transcript): array
    {
        $key = config('services.openai.api_key');

        if (! $key) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<'PROMPT'
You are an invoice parser. Extract structured data from voice transcripts.

Return ONLY a JSON object:
{
  "customer_name": "customer name or null",
  "items": [
    {
      "product_name": "product name as mentioned",
      "quantity": number
    }
  ]
}

Rules:
- Extract product names EXACTLY as mentioned (don't infer sizes/variants)
- Extract quantities as numbers
- If no customer mentioned, set customer_name to null
- If product mentioned without quantity, assume quantity = 1

Examples:
Input: "Create invoice for Ahmed with 5 Pepsi and 2 Coca Cola"
Output: {"customer_name": "Ahmed", "items": [{"product_name": "Pepsi", "quantity": 5}, {"product_name": "Coca Cola", "quantity": 2}]}

Input: "Invoice for Sarah 10 water bottles"
Output: {"customer_name": "Sarah", "items": [{"product_name": "water bottles", "quantity": 10}]}

Input: "Create invoice with 3 laptops"
Output: {"customer_name": null, "items": [{"product_name": "laptops", "quantity": 3}]}
PROMPT
                ],
                [
                    'role' => 'user',
                    'content' => "Transcript: \"{$transcript}\"",
                ],
            ],
            'max_tokens' => 300,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'AI parsing failed'];
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;
        $parsed = $content ? json_decode(trim($content), true) : null;

        if (! $parsed || ! isset($parsed['items'])) {
            return ['success' => false, 'error' => 'Could not extract order items'];
        }

        return ['success' => true, ...$parsed];
    }

    /**
     * Resolve customer by name
     */
    private function resolveCustomer(?string $name): CustomerResolutionResult
    {
        if (! $name) {
            return CustomerResolutionResult::needsClarificationResult(
                'Who is this invoice for?',
                []
            );
        }

        // Search clients in Daftra
        $response = $this->clients->list(['keywords' => $name, 'per_page' => 5]);

        if (empty($response->data)) {
            return CustomerResolutionResult::needsClarificationResult(
                "Customer '{$name}' not found. Would you like to create a new customer?",
                []
            );
        }

        $customers = $response->data;

        // Single exact match
        if (count($customers) === 1) {
            return CustomerResolutionResult::found($customers[0]);
        }

        // Multiple matches - need clarification
        $names = array_map(
            fn ($c) => ($c['Client']['business_name'] ?? $c['Client']['name']).
                      ($c['Client']['phone1'] ? " ({$c['Client']['phone1']})" : ''),
            array_slice($customers, 0, 3)
        );

        $question = count($names) === 2
            ? "Did you mean {$names[0]} or {$names[1]}?"
            : 'Which customer: '.implode(', ', $names).'?';

        return CustomerResolutionResult::needsClarificationResult($question, $customers);
    }

    /**
     * Apply user's product selection after clarification
     */
    public function applyProductSelection(array $lineItems, string $productQuery, int $selectedProductId): array
    {
        $product = $this->productMatcher->getById($selectedProductId);

        if (! $product) {
            throw new \Exception("Product ID {$selectedProductId} not found");
        }

        // Find and update the item
        return array_map(function ($item) use ($productQuery, $product) {
            if (($item['product_query'] ?? '') === $productQuery) {
                return [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'quantity' => $item['quantity'],
                    'price' => $product['unit_price'] ?? 0,
                    'total' => ($product['unit_price'] ?? 0) * $item['quantity'],
                ];
            }

            return $item;
        }, $lineItems);
    }
}

/**
 * Result of invoice preparation
 */
class InvoicePreparationResult
{
    public function __construct(
        public readonly string $status, // 'ready', 'needs_customer', 'needs_product', 'failed'
        public readonly ?array $customer = null,
        public readonly array $lineItems = [],
        public readonly float $total = 0,
        public readonly ?string $question = null,
        public readonly array $alternatives = [],
        public readonly ?string $error = null,
        public readonly ?string $originalTranscript = null,
        public readonly ?string $productQuery = null,
    ) {}

    public static function ready(array $customer, array $lineItems, float $total, string $originalTranscript): self
    {
        return new self(
            status: 'ready',
            customer: $customer,
            lineItems: $lineItems,
            total: $total,
            originalTranscript: $originalTranscript,
        );
    }

    public static function needsCustomerClarification(string $question, array $alternatives): self
    {
        return new self(
            status: 'needs_customer',
            question: $question,
            alternatives: $alternatives,
        );
    }

    public static function needsProductClarification(array $ambiguousProducts, array $matchedItems, ?array $customer = null): self
    {
        $firstAmbiguous = $ambiguousProducts[0];

        return new self(
            status: 'needs_product',
            customer: $customer,
            question: $firstAmbiguous['question'],
            alternatives: $firstAmbiguous['alternatives'],
            lineItems: $matchedItems, // Already matched items
            productQuery: $firstAmbiguous['query'] ?? null,
        );
    }

    public static function failed(string $error): self
    {
        return new self(status: 'failed', error: $error);
    }

    public function needsClarification(): bool
    {
        return in_array($this->status, ['needs_customer', 'needs_product']);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'customer' => $this->customer,
            'line_items' => $this->lineItems,
            'total' => $this->total,
            'needs_clarification' => $this->needsClarification(),
            'question' => $this->question,
            'alternatives' => $this->alternatives,
            'error' => $this->error,
        ];
    }
}

class CustomerResolutionResult
{
    public function __construct(
        public readonly string $status, // 'found', 'needs_clarification'
        public readonly ?array $customer = null,
        public readonly ?string $question = null,
        public readonly array $alternatives = [],
    ) {}

    public static function found(array $customer): self
    {
        return new self('found', $customer);
    }

    public static function needsClarificationResult(string $question, array $alternatives): self
    {
        return new self('needs_clarification', null, $question, $alternatives);
    }

    public function needsClarification(): bool
    {
        return $this->status === 'needs_clarification';
    }

    public function clarificationQuestion(): string
    {
        return $this->question ?? '';
    }
}
