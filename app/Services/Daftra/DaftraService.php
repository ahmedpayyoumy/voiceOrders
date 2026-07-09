<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Data\Data;
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
