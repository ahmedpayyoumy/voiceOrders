<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Data\InvoiceData;
use App\Services\Daftra\Data\InvoiceItemData;
use App\Services\Daftra\Interpreter\Intent;
use App\Services\Daftra\Interpreter\IntentType;
use App\Services\Daftra\Interpreter\QueryInterpreter;
use App\Services\Daftra\Interpreter\ResponseFormatter;
use App\Services\Daftra\Query\DaftraQuery;
use Illuminate\Support\Facades\Http;

class DaftraService
{
    private QueryInterpreter $interpreter;
    private ResponseFormatter $formatter;

    public function __construct(
        private readonly DaftraClient $client,
    ) {
        $this->interpreter = new QueryInterpreter();
        $this->formatter = new ResponseFormatter();
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
                    (int) ($intent->filters['id'] ?? 0),
                    $this->intentToData($intent)
                ),
                IntentType::Delete => $resource->delete(
                    (int) ($intent->filters['id'] ?? 0)
                ),
                default => throw new \InvalidArgumentException('Unknown intent type'),
            };

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
                    (new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(
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
