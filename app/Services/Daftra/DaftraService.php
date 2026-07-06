<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Data\AppointmentData;
use App\Services\Daftra\Data\ClientData;
use App\Services\Daftra\Data\ClientPaymentData;
use App\Services\Daftra\Data\CreditNoteData;
use App\Services\Daftra\Data\Data;
use App\Services\Daftra\Data\EstimateData;
use App\Services\Daftra\Data\ExpenseData;
use App\Services\Daftra\Data\FollowUpActionData;
use App\Services\Daftra\Data\FollowUpStatusData;
use App\Services\Daftra\Data\IncomeData;
use App\Services\Daftra\Data\InvoiceData;
use App\Services\Daftra\Data\InvoiceItemData;
use App\Services\Daftra\Data\InvoicePaymentData;
use App\Services\Daftra\Data\JournalAccountData;
use App\Services\Daftra\Data\JournalData;
use App\Services\Daftra\Data\NoteData;
use App\Services\Daftra\Data\ProductCategoryData;
use App\Services\Daftra\Data\ProductData;
use App\Services\Daftra\Data\PurchaseInvoiceData;
use App\Services\Daftra\Data\PurchaseRefundData;
use App\Services\Daftra\Data\RefundReceiptData;
use App\Services\Daftra\Data\StaffData;
use App\Services\Daftra\Data\StockTransactionData;
use App\Services\Daftra\Data\StoreData;
use App\Services\Daftra\Data\SupplierData;
use App\Services\Daftra\Data\TaxData;
use App\Services\Daftra\Data\TimeTrackingData;
use App\Services\Daftra\Data\TreasuryData;
use App\Services\Daftra\Data\WorkOrderData;
use App\Services\Daftra\Interpreter\Intent;
use App\Services\Daftra\Interpreter\IntentType;
use App\Services\Daftra\Interpreter\QueryInterpreter;
use App\Services\Daftra\Interpreter\ResponseFormatter;
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

    private function intentToData(Intent $intent): Data
    {
        $map = [
            'clients' => ClientData::class,
            'products' => ProductData::class,
            'product_categories' => ProductCategoryData::class,
            'invoices' => InvoiceData::class,
            'estimates' => EstimateData::class,
            'credit_notes' => CreditNoteData::class,
            'refund_receipts' => RefundReceiptData::class,
            'purchase_invoices' => PurchaseInvoiceData::class,
            'purchase_refunds' => PurchaseRefundData::class,
            'suppliers' => SupplierData::class,
            'work_orders' => WorkOrderData::class,
            'stores' => StoreData::class,
            'stock_transactions' => StockTransactionData::class,
            'expenses' => ExpenseData::class,
            'incomes' => IncomeData::class,
            'journals' => JournalData::class,
            'journal_accounts' => JournalAccountData::class,
            'taxes' => TaxData::class,
            'treasuries' => TreasuryData::class,
            'client_payments' => ClientPaymentData::class,
            'invoice_payments' => InvoicePaymentData::class,
            'staff' => StaffData::class,
            'notes' => NoteData::class,
            'time_tracking' => TimeTrackingData::class,
            'client_appointments' => AppointmentData::class,
            'follow_up_actions' => FollowUpActionData::class,
            'follow_up_statuses' => FollowUpStatusData::class,
        ];

        $class = $map[$intent->module] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("No Data class mapped for module: {$intent->module}");
        }

        return $class::fromArray($intent->data ?? []);
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
