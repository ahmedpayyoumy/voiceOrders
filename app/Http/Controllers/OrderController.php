<?php

namespace App\Http\Controllers;

use App\Http\Requests\DaftraInterpretRequest;
use App\Http\Requests\DaftraOrderRequest;
use App\Jobs\ProcessOrderWebhooks;
use App\Models\Order;
use App\Models\UsageLog;
use App\Services\Daftra\DaftraClient;
use App\Services\Daftra\DaftraException;
use App\Services\Daftra\DaftraService;
use App\Services\Daftra\Data\InvoiceData;
use App\Services\Daftra\Data\InvoiceItemData;
use App\Services\Daftra\Resources\ClientResource;
use App\Services\Daftra\Resources\InvoiceResource;
use App\Services\Daftra\VoiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'transcript' => 'required|string',
        ]);

        $user = $request->user();

        $order = Order::create([
            'user_id' => $user->id,
            'transcript' => $data['transcript'],
            'status' => 'pending',
        ]);

        UsageLog::create([
            'user_id' => $user->id,
            'action' => 'transcription',
            'metadata' => ['order_id' => $order->id],
        ]);

        $user->increment('used_this_month');

        ProcessOrderWebhooks::dispatch($order);

        return response()->json($order, 201);
    }

    public function index(Request $request)
    {
        return $request->user()->orders()->latest()->paginate(20);
    }

    public function sendToDaftra(DaftraOrderRequest $request)
    {
        $user = $request->user();
        $client = new DaftraClient($request->daftra_domain, $request->daftra_api_key);

        // Confirmed path: skip re-parsing, use submitted items directly
        if ($request->confirmed) {
            return $this->createInvoiceFromConfirmed($request, $user, $client);
        }

        $invoiceBuilder = new VoiceInvoiceBuilder($client);

        // Step 1: Prepare invoice with smart product matching
        $preparation = $invoiceBuilder->prepareInvoice(
            $request->transcript,
            $request->selected_customer_id,
            $request->selected_product_id,
            $request->selected_product_query,
            $request->selected_products ?? [],
        );

        // Step 2: If needs clarification, return question
        if ($preparation->needsClarification()) {
            $response = [
                'status' => 'needs_clarification',
                'question' => $preparation->question,
                'alternatives' => $preparation->alternatives,
                'matched_items' => $preparation->lineItems,
                'clarification_type' => $preparation->status,
            ];

            if ($preparation->customer) {
                $response['customer'] = $preparation->customer;
            }

            if ($preparation->productQuery) {
                $response['product_query'] = $preparation->productQuery;
            }

            return response()->json($response);
        }

        // Step 3: If preparation failed
        if ($preparation->status === 'failed') {
            return response()->json([
                'error' => $preparation->error,
            ], 422);
        }

        // Step 4: Return confirmation preview
        return response()->json([
            'status' => 'needs_confirmation',
            'customer' => $preparation->customer,
            'items' => $preparation->lineItems,
            'total' => $preparation->total,
            'original_transcript' => $request->transcript,
        ]);
    }

    private function createInvoiceFromConfirmed(DaftraOrderRequest $request, $user, DaftraClient $client): JsonResponse
    {
        $items = $request->items ?? [];

        // Fetch client from Daftra for display data
        $clientResource = new ClientResource($client);
        $customerData = [];

        try {
            $customerResponse = $clientResource->get((int) $request->selected_customer_id);
            $customerData = $customerResponse->data['Client'] ?? $customerResponse->data;
        } catch (DaftraException) {
            $customerData = ['id' => $request->selected_customer_id, 'name' => 'Client #'.$request->selected_customer_id];
        }

        $invoiceResource = new InvoiceResource($client);

        try {
            $response = $invoiceResource->create(
                new InvoiceData(
                    client_id: (int) ($customerData['id'] ?? $request->selected_customer_id),
                    issue_date: now()->format('Y-m-d'),
                    draft: true,
                    items: array_map(
                        fn ($item) => new InvoiceItemData(
                            product_id: (int) $item['product_id'],
                            quantity: (float) $item['quantity'],
                            unit_price: (float) $item['price'],
                        ),
                        $items
                    ),
                )
            );

            $invoiceId = $response->id;
            $invoiceNumber = $response->raw['invoice_number'] ?? ('#'.$invoiceId);

            $order = Order::create([
                'user_id' => $user->id,
                'transcript' => $request->transcript,
                'parsed_data' => [
                    'items' => $items,
                    'customer_id' => $request->selected_customer_id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceId,
                ],
                'status' => 'sent',
                'response_log' => [['daftra' => $response->raw]],
            ]);

            UsageLog::create([
                'user_id' => $user->id,
                'action' => 'daftra_invoice',
                'metadata' => [
                    'order_id' => $order->id,
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'total' => array_sum(array_column($items, 'total')) ?: 0,
                ],
            ]);

            $user->increment('used_this_month');

            return response()->json([
                'status' => 'success',
                'order' => $order,
                'invoice' => $response->raw,
                'preview' => [
                    'customer' => $customerData['business_name'] ?? $customerData['name'],
                    'items' => $items,
                    'total' => array_sum(array_column($items, 'total')) ?: 0,
                    'invoice_number' => $invoiceNumber,
                ],
            ], 201);

        } catch (DaftraException $e) {
            logger()->error('Daftra create invoice failed', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $e->responseData,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Daftra API error: '.$e->getMessage(),
                'error_code' => $e->getCode(),
                'error_response' => $e->responseData,
            ], 500);
        }
    }

    public function interpret(DaftraInterpretRequest $request)
    {
        $domain = $request->daftra_domain;
        $apiKey = $request->daftra_api_key;

        if ($domain && $apiKey) {
            $daftra = new DaftraService(new DaftraClient($domain, $apiKey));
            $result = $daftra->interpretAndExecute($request->transcript);
        } else {
            $daftra = new DaftraService(
                new DaftraClient('placeholder', 'placeholder')
            );
            $intent = $daftra->interpret($request->transcript);
            $result = [
                'success' => true,
                'intent' => $intent->toArray(),
                'needs_clarification' => ! $intent->isComplete,
                'question' => $intent->clarification,
            ];
        }

        return response()->json($result);
    }
}
