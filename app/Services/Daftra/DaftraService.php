<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Data\Data;
use App\Services\Daftra\Data\InvoiceData;
use App\Services\Daftra\Data\InvoiceItemData;
use App\Services\Daftra\Data\RequisitionData;
use App\Services\Daftra\Data\RequisitionItemData;
use App\Services\Daftra\Interpreter\Intent;
use App\Services\Daftra\Interpreter\IntentType;
use App\Services\Daftra\Interpreter\QueryInterpreter;
use App\Services\Daftra\Interpreter\ResponseFormatter;
use App\Services\Daftra\Resources\Resource;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;

class DaftraService
{
    private QueryInterpreter $interpreter;

    private ResponseFormatter $formatter;

    public function __construct(
        private readonly DaftraClient $client,
    ) {
        $this->interpreter = new QueryInterpreter;
        $this->formatter = new ResponseFormatter;
    }

    public function client(): DaftraClient
    {
        return $this->client;
    }

    public function interpret(string $transcript): Intent
    {
        return $this->interpreter->interpret($transcript);
    }

    public function execute(Intent $intent): array
    {
        $resource = $this->client->module($intent->module);

        $resourceId = (int) ($intent->filters['id'] ?? 0);

        $data = $intent->data ?? [];

        $quantity = $data['quantity'] ?? null;
        $requisitionType = $data['requisition_type'] ?? null;
        $storeId = $data['store_id'] ?? null;

        if ($resourceId === 0 && in_array($intent->type, [IntentType::Update, IntentType::Delete], true)) {
            $resolvedId = $this->resolveResourceId($intent, $resource);

            if ($resolvedId === null) {
                return [
                    'success' => false,
                    'error' => 'Resource not found matching the given criteria',
                    'intent' => $intent->toArray(),
                ];
            }

            $resourceId = $resolvedId;
        }

        try {
            if ($intent->type === IntentType::List && $intent->module === 'invoices') {
                $invoiceListResult = $this->executeInvoiceListIntent($intent, $resource);
                if ($invoiceListResult !== null) {
                    return $invoiceListResult;
                }
            }

            $response = match ($intent->type) {
                IntentType::List, IntentType::Count => $resource->list(
                    $intent->toQuery()->toQueryParams()
                ),
                IntentType::Get => $resource->get(
                    (int) ($intent->filters['id'] ?? 0)
                ),
                IntentType::Create => $resource->create(
                    $this->intentToData($intent)
                ),
                IntentType::Update => $resource->update(
                    $resourceId,
                    $this->mergeUpdateData($resource, $resourceId, $intent)
                ),
                IntentType::Delete => $resource->delete(
                    $resourceId
                ),
                default => throw new \InvalidArgumentException('Unknown intent type'),
            };

            $this->handleProductRequisition($intent, $response, $resourceId, $quantity, $requisitionType, $storeId);

            $formatted = $this->formatter->format($response, $intent);

            return [
                'success' => true,
                'response' => $response,
                'formatted' => $formatted,
                'intent' => $intent->toArray(),
            ];
        } catch (DaftraException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'intent' => $intent->toArray(),
            ];
        }
    }

    private function executeInvoiceListIntent(Intent $intent, Resource $invoiceResource): ?array
    {
        $clientId = (int) ($intent->filters['client_id'] ?? 0);
        $selectedClient = null;

        if ($clientId === 0) {
            $clientSearchName = $this->extractClientSearchName($intent);

            if ($clientSearchName === null) {
                return null;
            }

            $resolvedClient = $this->resolveClientByName($clientSearchName);

            if ($resolvedClient['status'] === 'not_found') {
                return [
                    'success' => false,
                    'error' => "Client '{$clientSearchName}' not found.",
                    'intent' => $intent->toArray(),
                ];
            }

            if ($resolvedClient['status'] === 'needs_clarification') {
                return [
                    'success' => true,
                    'status' => 'needs_clarification',
                    'clarification_type' => 'needs_customer',
                    'needs_clarification' => true,
                    'question' => $resolvedClient['question'],
                    'alternatives' => $resolvedClient['alternatives'],
                    'intent' => $intent->toArray(),
                ];
            }

            $clientId = (int) $resolvedClient['client_id'];
            $selectedClient = $resolvedClient['client'];
        } else {
            $clientResponse = $this->client->module('clients')->get($clientId);
            $selectedClient = $clientResponse->entity('Client') ?? $clientResponse->data;
        }

        $queryParams = $this->buildInvoiceListQueryParams($intent, $clientId);
        $response = $invoiceResource->list($queryParams);

        $invoices = $this->mapInvoiceRows($response->data ?? []);
        $summary = $this->buildInvoiceSummary($invoices, $response);
        $clientName = $this->clientDisplayName($selectedClient ?? []);
        $count = count($invoices);

        return [
            'success' => true,
            'status' => 'invoice_list',
            'selected_client' => $selectedClient,
            'invoices' => $invoices,
            'summary' => $summary,
            'formatted' => "Found {$count} invoice(s) for {$clientName}.",
            'intent' => $intent->toArray(),
        ];
    }

    private function extractClientSearchName(Intent $intent): ?string
    {
        $fields = [
            'client',
            'client_name',
            'client_business_name',
            'business_name',
            'name',
            'first_name',
        ];

        foreach ($fields as $field) {
            $value = $intent->filters[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $query = trim((string) ($intent->originalQuery ?? ''));
        if ($query === '') {
            return null;
        }

        if (preg_match('/(?:للعميل|للزبون|العميل|الزبون|client)\s+([^\d\n]+)/iu', $query, $matches) === 1) {
            $name = trim($matches[1]);
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function resolveClientByName(string $name): array
    {
        $terms = array_slice($this->buildClientSearchTerms($name), 0, 10);
        $candidates = $this->searchClientsByTerms($terms);

        if (empty($candidates)) {
            return ['status' => 'not_found'];
        }

        $ranked = $this->rankClientCandidates($name, $terms, $candidates);

        if (count($ranked) === 1) {
            $client = $ranked[0]['raw'];

            return [
                'status' => 'matched',
                'client_id' => (int) ($client['id'] ?? 0),
                'client' => $client,
            ];
        }

        $top = $ranked[0] ?? null;
        $second = $ranked[1] ?? null;

        if ($top !== null && ($top['score'] >= 95) && ($second === null || ($top['score'] - $second['score']) >= 15)) {
            return [
                'status' => 'matched',
                'client_id' => (int) ($top['raw']['id'] ?? 0),
                'client' => $top['raw'],
            ];
        }

        $alternatives = array_map(
            fn (array $candidate): array => ['Client' => $candidate['raw']],
            array_slice($ranked, 0, 5),
        );

        return [
            'status' => 'needs_clarification',
            'question' => 'Which customer did you mean?',
            'alternatives' => $alternatives,
        ];
    }

    private function searchClientsByTerms(array $terms): array
    {
        $results = [];
        $seenIds = [];

        foreach ($terms as $term) {
            if (! is_string($term) || trim($term) === '') {
                continue;
            }

            $response = $this->client->module('clients')->list([
                'keywords' => trim($term),
                'per_page' => 10,
            ]);

            foreach (($response->data ?? []) as $candidate) {
                $client = $candidate['Client'] ?? $candidate;
                $id = $client['id'] ?? null;

                if ($id === null) {
                    continue;
                }

                if (isset($seenIds[$id])) {
                    continue;
                }

                $seenIds[$id] = true;
                $results[] = ['Client' => $client];
            }

            // Stop early once we have a usable hit set; no need to query every transliteration.
            if (! empty($results)) {
                break;
            }

            if (count($results) >= 10) {
                break;
            }
        }

        return $results;
    }

    private function buildClientSearchTerms(string $name): array
    {
        $terms = [$name];
        $normalized = $this->normalizeText($name);

        $commonArabicNames = [
            'وليد' => ['Waleed', 'Walid', 'Waled'],
            'محمد' => ['Mohammed', 'Mohamed', 'Muhammad'],
            'احمد' => ['Ahmed', 'Ahmad'],
            'خالد' => ['Khaled', 'Khalid'],
        ];

        foreach ($commonArabicNames as $arabic => $variants) {
            if (str_contains($normalized, $arabic)) {
                foreach ($variants as $variant) {
                    $terms[] = $variant;
                }
            }
        }

        if (Transliteration::isArabic($name)) {
            foreach (Transliteration::transliterateAlternatives($name) as $candidate) {
                $terms[] = $candidate;
            }
        }

        foreach (preg_split('/\s+/u', trim($name)) ?: [] as $token) {
            if ($token !== '') {
                $terms[] = $token;
            }
        }

        $caseVariants = [];
        foreach ($terms as $term) {
            $caseVariants[] = mb_strtolower((string) $term, 'UTF-8');
        }

        $terms = array_merge($terms, $caseVariants);

        return array_values(array_unique(array_filter(array_map('trim', $terms))));
    }

    private function rankClientCandidates(string $rawName, array $terms, array $candidates): array
    {
        $rawNormalized = $this->normalizeText($rawName);
        $termNormalized = array_map(fn (string $term): string => $this->normalizeText($term), $terms);

        $ranked = [];

        foreach ($candidates as $candidate) {
            $client = $candidate['Client'] ?? $candidate;
            $name = $this->clientDisplayName($client);
            $normalizedName = $this->normalizeText($name);

            $score = 0;

            if ($normalizedName === $rawNormalized) {
                $score += 100;
            }

            if (str_contains($normalizedName, $rawNormalized)) {
                $score += 40;
            }

            foreach ($termNormalized as $term) {
                if ($term === '') {
                    continue;
                }

                if ($normalizedName === $term) {
                    $score += 40;

                    continue;
                }

                if (str_contains($normalizedName, $term)) {
                    $score += 20;
                }
            }

            $ranked[] = [
                'score' => $score,
                'raw' => $client,
            ];
        }

        usort($ranked, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $ranked;
    }

    private function buildInvoiceListQueryParams(Intent $intent, int $clientId): array
    {
        $limit = $this->extractRequestedLimit($intent);

        $params = [
            'client_id' => $clientId,
            'limit' => $limit,
            'page' => 1,
            'sort' => 'date',
            'direction' => 'DESC',
        ];

        $dateFrom = $intent->filters['date_from'] ?? ($intent->data['date_from'] ?? null);
        $dateTo = $intent->filters['date_to'] ?? ($intent->data['date_to'] ?? null);

        if (is_string($dateFrom) && trim($dateFrom) !== '') {
            $params['date_from'] = trim($dateFrom);
        }

        if (is_string($dateTo) && trim($dateTo) !== '') {
            $params['date_to'] = trim($dateTo);
        }

        return $params;
    }

    private function extractRequestedLimit(Intent $intent): int
    {
        $limitCandidates = [
            $intent->filters['limit'] ?? null,
            $intent->filters['per_page'] ?? null,
            $intent->filters['count'] ?? null,
            $intent->data['limit'] ?? null,
            $intent->data['count'] ?? null,
        ];

        foreach ($limitCandidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return min((int) $candidate, 50);
            }
        }

        $query = $intent->originalQuery ?? '';
        if (preg_match('/(?:last|latest|اخر|آخر)\s+([0-9٠-٩]+)/iu', $query, $matches) === 1) {
            $parsed = (int) $this->normalizeDigits($matches[1]);
            if ($parsed > 0) {
                return min($parsed, 50);
            }
        }

        if (preg_match('/([0-9٠-٩]+)\s*(?:فواتير|فاتوره|فاتورة|invoices?)/iu', $query, $matches) === 1) {
            $parsed = (int) $this->normalizeDigits($matches[1]);
            if ($parsed > 0) {
                return min($parsed, 50);
            }
        }

        return 5;
    }

    private function mapInvoiceRows(array $items): array
    {
        return array_map(function (array $item): array {
            $invoice = $item['Invoice'] ?? $item;

            $total = (float) ($invoice['summary_total'] ?? $invoice['total'] ?? 0);
            $paid = (float) ($invoice['paid'] ?? $invoice['amount_paid'] ?? 0);
            $balance = (float) ($invoice['due'] ?? ($invoice['amount_due'] ?? ($total - $paid)));

            return [
                'id' => (int) ($invoice['id'] ?? 0),
                'number' => (string) ($invoice['invoice_number'] ?? $invoice['no'] ?? $invoice['name'] ?? ''),
                'date' => $invoice['date'] ?? $invoice['issue_date'] ?? null,
                'due_date' => $invoice['due_date'] ?? null,
                'status' => (string) ($invoice['status'] ?? $invoice['payment_status'] ?? ''),
                'currency' => (string) ($invoice['currency_code'] ?? 'SAR'),
                'total' => $total,
                'paid' => $paid,
                'balance' => $balance,
            ];
        }, $items);
    }

    private function buildInvoiceSummary(array $invoices, DaftraResponse $response): array
    {
        $total = array_sum(array_column($invoices, 'total'));
        $paid = array_sum(array_column($invoices, 'paid'));
        $balance = array_sum(array_column($invoices, 'balance'));

        return [
            'count' => count($invoices),
            'total' => round($total, 2),
            'paid' => round($paid, 2),
            'balance' => round($balance, 2),
            'pagination_total' => $response->pagination['total'] ?? count($invoices),
        ];
    }

    private function clientDisplayName(array $client): string
    {
        $name = trim((string) ($client['business_name'] ?? $client['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) (($client['first_name'] ?? '').' '.($client['last_name'] ?? ''))) ?: 'Client';
    }

    private function normalizeText(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');

        return preg_replace('/[^\p{Arabic}a-z0-9]+/iu', '', $lower) ?? '';
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    private function handleProductRequisition(Intent $intent, DaftraResponse $response, int $resourceId, mixed $quantity, mixed $requisitionType, ?int $storeId): void
    {
        if ($intent->module !== 'products' || ! $response->successful() || $quantity === null) {
            return;
        }

        $productId = $intent->type === IntentType::Create ? $response->id : $resourceId;

        if ($productId === null) {
            return;
        }

        $this->createRequisition(
            productId: $productId,
            quantity: abs((int) $quantity),
            storeId: $storeId,
            type: (int) ($requisitionType ?? 1),
        );
    }

    private function createRequisition(int $productId, int $quantity, ?int $storeId, int $type): void
    {
        $requisitionData = new RequisitionData(
            store_id: $storeId,
            type: $type,
            order_type: $type,
            items: [
                new RequisitionItemData(
                    product_id: $productId,
                    quantity: abs((int) $quantity),
                ),
            ],
        );

        $this->client->module('requisitions')->create($requisitionData);
    }

    public function interpretAndExecute(string $transcript): array
    {
        $intent = $this->interpret($transcript);

        if (! $intent->isComplete) {
            return [
                'success' => true,
                'needs_clarification' => true,
                'intent' => $intent->toArray(),
                'question' => $intent->clarification ?? 'What would you like to do?',
            ];
        }

        return $this->execute($intent);
    }

    private function intentToData(Intent $intent): Data
    {
        $map = ModuleRegistry::all();

        $class = $map[$intent->module] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("No Data class mapped for module: {$intent->module}");
        }

        return $class::fromArray($intent->data ?? []);
    }

    private function resolveResourceId(Intent $intent, Resource $resource): ?int
    {
        $searchableFields = ['first_name', 'last_name', 'business_name', 'name', 'email', 'phone', 'mobile'];

        $terms = [];
        foreach ($searchableFields as $field) {
            if (! empty($intent->filters[$field]) && is_string($intent->filters[$field])) {
                $terms[] = $intent->filters[$field];
            }
        }

        if (empty($terms)) {
            foreach ($intent->filters as $field => $value) {
                if (is_string($value) && ! empty($value) && $field !== 'id') {
                    $terms[] = $value;
                }
            }
        }

        if (empty($terms)) {
            return null;
        }

        $keywords = implode(' ', $terms);

        $response = $resource->list(['keywords' => $keywords, 'per_page' => 10]);
        $items = $response->data ?? [];

        if (empty($items)) {
            return null;
        }

        $item = $items[0];
        $entityKey = $resource->entityKey();

        if ($entityKey && isset($item[$entityKey]['id'])) {
            return (int) $item[$entityKey]['id'];
        }

        return ($id = $item['id'] ?? 0) ? (int) $id : null;
    }

    private function mergeUpdateData(Resource $resource, int $resourceId, Intent $intent): Data
    {
        $map = ModuleRegistry::all();
        $class = $map[$intent->module] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("No Data class mapped for module: {$intent->module}");
        }

        $existingResponse = $resource->get($resourceId);
        $entityKey = $resource->entityKey();
        $existingData = $existingResponse->entity($entityKey) ?? [];

        $updateData = $class::fromArray($intent->data ?? [])->toArray()[$entityKey] ?? [];

        $merged = array_merge($existingData, $updateData);
        unset($merged['id']);

        return $class::fromArray($merged);
    }

    public function processVoiceOrder(string $transcript, ?string $apiKey = null, ?string $domain = null): array
    {
        $key = config('services.openai.api_key');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You parse voice transcripts into structured order data. Extract order items with names, quantities, and estimated prices. Also extract any client/business name mentioned. Return ONLY JSON: { "client_name": "...", "items": [{ "item": "...", "quantity": N, "unit_price": N }] }',
                ],
                [
                    'role' => 'user',
                    'content' => "Transcript: \"{$transcript}\"",
                ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Failed to parse transcript'];
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;
        $parsed = $content ? json_decode(trim($content), true) : null;

        if (! $parsed || ! isset($parsed['items'])) {
            return ['success' => false, 'error' => 'Could not extract order items from transcript'];
        }

        $clientName = $parsed['client_name'] ?? 'Voice Order';
        $items = array_map(
            fn ($i) => new InvoiceItemData(
                item: $i['item'] ?? 'Item',
                quantity: $i['quantity'] ?? 1,
                unit_price: $i['unit_price'] ?? 0,
            ),
            $parsed['items'],
        );

        if (! $domain || ! $apiKey) {
            return [
                'success' => true,
                'needs_credentials' => true,
                'parsed' => [
                    'client_name' => $clientName,
                    'items' => array_map(fn (InvoiceItemData $i) => [
                        'item' => $i->item,
                        'quantity' => $i->quantity,
                        'unit_price' => $i->unit_price,
                    ], $items),
                ],
            ];
        }

        try {
            $tempClient = new DaftraClient($domain, $apiKey);

            $invoiceData = new InvoiceData(
                client_business_name: $clientName,
                notes: "Original transcript: {$transcript}",
                items: $items,
            );

            $response = $tempClient->invoices()->create($invoiceData);

            return [
                'success' => true,
                'daftra_response' => new DaftraResponse(
                    (new \Illuminate\Http\Client\Response(new Response(
                        $response->successful() ? 200 : 400,
                        [],
                        json_encode($response->raw),
                    )))
                ),
                'parsed' => [
                    'client_name' => $clientName,
                    'items' => array_map(fn (InvoiceItemData $i) => [
                        'item' => $i->item,
                        'quantity' => $i->quantity,
                        'unit_price' => $i->unit_price,
                    ], $items),
                ],
            ];
        } catch (DaftraException $e) {
            return [
                'success' => false,
                'error' => 'Daftra error: '.$e->getMessage(),
                'parsed' => [
                    'client_name' => $clientName,
                    'items' => array_map(fn (InvoiceItemData $i) => [
                        'item' => $i->item,
                        'quantity' => $i->quantity,
                        'unit_price' => $i->unit_price,
                    ], $items),
                ],
            ];
        }
    }
}
