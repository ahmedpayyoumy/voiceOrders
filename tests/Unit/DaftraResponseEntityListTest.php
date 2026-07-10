<?php

namespace Tests\Unit;

use App\Services\Daftra\DaftraResponse;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class DaftraResponseEntityListTest extends TestCase
{
    public function test_entity_list_auto_detects_wrapper_key(): void
    {
        $httpResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'code' => 200,
            'result' => 'successful',
            'data' => [
                ['Invoice' => ['id' => 1, 'no' => 'INV-001', 'summary_total' => 100]],
                ['Invoice' => ['id' => 2, 'no' => 'INV-002', 'summary_total' => 200]],
            ],
            'pagination' => ['total' => 2],
        ]));

        $laravelResponse = new class($httpResponse) extends Response
        {
            public function __construct($psrResponse)
            {
                $this->response = $psrResponse;
            }
        };

        $daftraResponse = new DaftraResponse($laravelResponse);

        // Calling with plural 'Invoices' should auto-detect 'Invoice' key
        $result = $daftraResponse->entityList('Invoices');

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('INV-001', $result[0]['no']);
        $this->assertSame(100, $result[0]['summary_total']);
        $this->assertSame(200, $result[1]['summary_total']);
    }

    public function test_entity_list_with_exact_key_still_works(): void
    {
        $httpResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'code' => 200,
            'result' => 'successful',
            'data' => [
                ['Invoice' => ['id' => 1]],
                ['Invoice' => ['id' => 2]],
            ],
        ]));

        $laravelResponse = new class($httpResponse) extends Response
        {
            public function __construct($psrResponse)
            {
                $this->response = $psrResponse;
            }
        };

        $daftraResponse = new DaftraResponse($laravelResponse);

        // Calling with exact key 'Invoice' should work
        $result = $daftraResponse->entityList('Invoice');

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
    }

    public function test_entity_list_returns_empty_for_no_data(): void
    {
        $httpResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'code' => 200,
            'result' => 'successful',
            'data' => [],
        ]));

        $laravelResponse = new class($httpResponse) extends Response
        {
            public function __construct($psrResponse)
            {
                $this->response = $psrResponse;
            }
        };

        $daftraResponse = new DaftraResponse($laravelResponse);

        $result = $daftraResponse->entityList('Invoices');

        $this->assertSame([], $result);
    }

    public function test_entity_list_returns_flat_data_as_is(): void
    {
        $httpResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'code' => 200,
            'result' => 'successful',
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ]));

        $laravelResponse = new class($httpResponse) extends Response
        {
            public function __construct($psrResponse)
            {
                $this->response = $psrResponse;
            }
        };

        $daftraResponse = new DaftraResponse($laravelResponse);

        $result = $daftraResponse->entityList('Invoices');

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Item 1', $result[0]['name']);
    }

    public function test_entity_list_does_not_auto_detect_unrelated_wrapper_key(): void
    {
        $httpResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'code' => 200,
            'result' => 'successful',
            'data' => [
                ['Invoice' => ['id' => 1]],
                ['Invoice' => ['id' => 2]],
            ],
        ]));

        $laravelResponse = new class($httpResponse) extends Response
        {
            public function __construct($psrResponse)
            {
                $this->response = $psrResponse;
            }
        };

        $daftraResponse = new DaftraResponse($laravelResponse);

        // When a key doesn't match, auto-detection finds 'Invoice' wrapper
        $result = $daftraResponse->entityList('Client');

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
    }
}
