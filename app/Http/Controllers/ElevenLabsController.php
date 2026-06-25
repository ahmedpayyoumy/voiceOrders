<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ElevenLabsController extends Controller
{
    public function token(Request $request)
    {
        $key = config('services.elevenlabs.api_key');

        if (! $key) {
            return response()->json(['error' => 'ElevenLabs API key not configured'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $key,
        ])->post('https://api.elevenlabs.io/v1/single-use-token/realtime_scribe');

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to generate ElevenLabs token: '.$response->body(),
            ], $response->status());
        }

        $data = $response->json();

        return response()->json([
            'token' => $data['token'],
            'model_id' => 'scribe_v2_realtime',
            'ws_url' => 'wss://api.elevenlabs.io/v1/realtime-transcription',
        ]);
    }
}
