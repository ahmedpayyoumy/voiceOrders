<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\UsageLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class ProcessOrderWebhooks implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 30;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(): void
    {
        $user = $this->order->user;
        $endpoints = $user->webhookEndpoints()->where('is_active', true)->get();

        if ($endpoints->isEmpty()) {
            $this->order->update(['status' => 'sent']);
            return;
        }

        $responses = [];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::withHeaders($endpoint->headers ?? [])
                    ->send($endpoint->method, $endpoint->url, [
                        'json' => [
                            'transcript' => $this->order->transcript,
                            'order_id' => $this->order->id,
                            'user_id' => $user->id,
                            'timestamp' => $this->order->created_at,
                        ],
                    ]);

                $responses[] = [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_name' => $endpoint->name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];

                UsageLog::create([
                    'user_id' => $user->id,
                    'action' => 'webhook',
                    'metadata' => [
                        'order_id' => $this->order->id,
                        'endpoint_id' => $endpoint->id,
                        'response_status' => $response->status(),
                    ],
                ]);
            } catch (\Exception $e) {
                $responses[] = [
                    'endpoint_id' => $endpoint->id,
                    'endpoint_name' => $endpoint->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->order->update([
            'status' => 'sent',
            'response_log' => $responses,
        ]);
    }
}
