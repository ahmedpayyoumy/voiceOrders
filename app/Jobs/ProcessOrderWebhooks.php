<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\UsageLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;

class ProcessOrderWebhooks implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $timeout = 60;

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
            $payload = $this->buildPayload($endpoint);

            try {
                $response = Http::withHeaders($endpoint->headers ?? [])
                    ->timeout(15)
                    ->send($endpoint->method, $endpoint->url, [
                        'json' => $payload,
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

    protected function buildPayload($endpoint): array
    {
        $base = [
            'transcript' => $this->order->transcript,
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'timestamp' => $this->order->created_at,
        ];

        if (! $endpoint->transform_prompt) {
            return $base;
        }

        $parsed = $this->parseWithLLM($endpoint->transform_prompt);

        if ($parsed === null) {
            return $base;
        }

        $this->order->update(['parsed_data' => $parsed]);

        return array_merge($base, ['parsed' => $parsed]);
    }

    protected function parseWithLLM(string $prompt): ?array
    {
        $key = config('services.openai.api_key');

        if (! $key) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a transcript parser. Given a transcript and a transformation instruction, return valid JSON matching the instruction. Only respond with the JSON object, no explanation or markdown formatting.',
                ],
                [
                    'role' => 'user',
                    'content' => "Transcript: \"{$this->order->transcript}\"\n\nInstruction: {$prompt}",
                ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            return null;
        }

        $decoded = json_decode(trim($content), true);

        return is_array($decoded) ? $decoded : null;
    }
}
