<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DaftraInterpretInvoiceListTest extends TestCase
{
    public function test_it_returns_customer_clarification_for_invoice_listing_when_multiple_clients_match(): void
    {
        Sanctum::actingAs(User::factory()->make(['id' => 1]));

        config()->set('services.openai.api_key', 'test-key');
        config()->set('logging.channels.daftra', ['driver' => 'null']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'api.openai.com/v1/chat/completions')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'type' => 'list',
                                'module' => 'invoices',
                                'filters' => [
                                    'client_name' => 'وليد',
                                ],
                                'data' => null,
                                'is_complete' => true,
                                'clarification' => null,
                            ]),
                        ],
                    ]],
                ], 200);
            }

            if (str_contains($url, '.daftra.com/api2/clients')) {
                return Http::response([
                    'code' => 200,
                    'result' => 'successful',
                    'data' => [
                        [
                            'Client' => [
                                'id' => 11,
                                'business_name' => 'Waleed Trading',
                            ],
                        ],
                        [
                            'Client' => [
                                'id' => 12,
                                'business_name' => 'Walid Store',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->postJson('/api/daftra/interpret', [
            'transcript' => 'هاتلى اخر 3 فواتير للعميل وليد',
            'daftra_domain' => 'demo',
            'daftra_api_key' => 'token',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'needs_clarification',
                'clarification_type' => 'needs_customer',
                'needs_clarification' => true,
            ]);

        $this->assertNotEmpty($response->json('alternatives'));
    }

    public function test_it_lists_latest_invoices_for_selected_customer_with_summary(): void
    {
        Sanctum::actingAs(User::factory()->make(['id' => 2]));

        config()->set('services.openai.api_key', 'test-key');
        config()->set('logging.channels.daftra', ['driver' => 'null']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'api.openai.com/v1/chat/completions')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'type' => 'list',
                                'module' => 'invoices',
                                'filters' => [],
                                'data' => null,
                                'is_complete' => true,
                                'clarification' => null,
                            ]),
                        ],
                    ]],
                ], 200);
            }

            if (str_contains($url, '.daftra.com/api2/clients/15')) {
                return Http::response([
                    'code' => 200,
                    'result' => 'successful',
                    'data' => [
                        'Client' => [
                            'id' => 15,
                            'business_name' => 'Waleed Corp',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '.daftra.com/api2/invoices')) {
                $this->assertStringContainsString('client_id=15', $url);
                $this->assertStringContainsString('limit=3', $url);

                return Http::response([
                    'code' => 200,
                    'result' => 'successful',
                    'pagination' => ['total' => 3],
                    'data' => [
                        [
                            'Invoice' => [
                                'id' => 701,
                                'invoice_number' => 'INV-701',
                                'date' => '2026-07-10',
                                'status' => 'paid',
                                'summary_total' => 100,
                                'amount_paid' => 100,
                                'amount_due' => 0,
                            ],
                        ],
                        [
                            'Invoice' => [
                                'id' => 700,
                                'invoice_number' => 'INV-700',
                                'date' => '2026-07-09',
                                'status' => 'partial',
                                'summary_total' => 80,
                                'amount_paid' => 30,
                                'amount_due' => 50,
                            ],
                        ],
                        [
                            'Invoice' => [
                                'id' => 699,
                                'invoice_number' => 'INV-699',
                                'date' => '2026-07-08',
                                'status' => 'unpaid',
                                'summary_total' => 20,
                                'amount_paid' => 0,
                                'amount_due' => 20,
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->postJson('/api/daftra/interpret', [
            'transcript' => 'هاتلى اخر 3 فواتير للعميل وليد',
            'daftra_domain' => 'demo',
            'daftra_api_key' => 'token',
            'selected_customer_id' => 15,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'invoice_list',
                'summary' => [
                    'count' => 3,
                    'total' => 200.0,
                    'paid' => 130.0,
                    'balance' => 70.0,
                    'pagination_total' => 3,
                ],
            ]);

        $this->assertCount(3, $response->json('invoices'));
        $this->assertSame('Waleed Corp', $response->json('selected_client.business_name'));
    }
}
