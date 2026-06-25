<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderWebhooks;
use App\Models\Order;
use App\Models\UsageLog;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'transcript' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->used_this_month >= $user->monthly_quota) {
            return response()->json(['error' => 'Monthly quota exceeded'], 429);
        }

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
}
