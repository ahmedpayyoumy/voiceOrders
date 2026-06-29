<?php

namespace App\Http\Controllers;

use App\Http\Requests\DaftraInterpretRequest;
use App\Http\Requests\DaftraOrderRequest;
use App\Jobs\ProcessOrderWebhooks;
use App\Models\Order;
use App\Models\UsageLog;
use App\Services\Daftra\DaftraClient;
use App\Services\Daftra\DaftraService;
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

        $daftra = new DaftraService(
            new DaftraClient($request->daftra_domain, $request->daftra_api_key)
        );

        $result = $daftra->processVoiceOrder(
            transcript: $request->transcript,
            apiKey: $request->daftra_api_key,
            domain: $request->daftra_domain,
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to process order'], 500);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'transcript' => $request->transcript,
            'parsed_data' => $result['parsed'] ?? null,
            'status' => 'sent',
            'response_log' => isset($result['daftra_response'])
                ? [['daftra' => $result['daftra_response']->raw]]
                : null,
        ]);

        UsageLog::create([
            'user_id' => $user->id,
            'action' => 'daftra_order',
            'metadata' => [
                'order_id' => $order->id,
                'domain' => $request->daftra_domain,
                'items' => $result['parsed']['items'] ?? [],
            ],
        ]);

        $user->increment('used_this_month');

        return response()->json([
            'order' => $order,
            'parsed' => $result['parsed'] ?? null,
            'daftra' => $result['daftra_response'] ?? null,
        ], 201);
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
